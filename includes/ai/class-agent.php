<?php
/**
 * RecruitTech AI Agent
 *
 * Implements the "Agent (Action)" part of the AI Trinity described in the
 * project proposal. When a recruiter clicks "Analyze with AI" on an
 * application, recruittech_ai_analyze_application():
 *   1. Retrieves the candidate's CV text.
 *   2. Retrieves the job description/requirements.
 *   3. Retrieves the company's recruitment documents (lightweight RAG).
 *   4. Builds a complete AI prompt.
 *   5. Sends the request to the LLM (RecruitTech_AI_Client).
 *   6. Saves the generated analysis into the database.
 *   7. Returns the final hiring report to the caller (the AJAX handler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert a row into the AI analysis log table.
 *
 * @param wpdb $wpdb WordPress database object.
 * @param int $application_id Application ID.
 * @param string $prompt_text Prompt text to record.
 * @param string $response_text Response text to record.
 * @return void
 */
function recruittech_ai_insert_analysis_log( $wpdb, $application_id, $prompt_text, $response_text ) {
	$log_table = $wpdb->prefix . 'recruitech_ai_analysis_log';
	$result = $wpdb->insert(
		$log_table,
		array(
			'application_id'    => absint( $application_id ),
			'prompt_sent'       => substr( (string) $prompt_text, 0, 20000 ),
			'response_received' => substr( (string) $response_text, 0, 20000 ),
			'created_at'        => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		error_log( 'RecruitTech AI log insert failed: ' . $wpdb->last_error );
	}
}

/**
 * Run the AI Agent for a single application.
 *
 * @param int $application_id Application ID.
 * @param int $company_id     Company ID that must own the underlying job (ownership check).
 * @return array|WP_Error Structured analysis result, or WP_Error on failure.
 */
function recruittech_ai_analyze_application( $application_id, $company_id, $force_refresh = false ) {
	global $wpdb;

	$application_id = absint( $application_id );
	$company_id     = absint( $company_id );

	if ( ! $application_id || ! $company_id ) {
		recruittech_ai_insert_analysis_log( $wpdb, $application_id, 'invalid request', 'invalid request' );
		return new WP_Error( 'recruittech_ai_invalid_request', 'Invalid application.' );
	}

	$applications_table = $wpdb->prefix . 'recruitech_applications';
	$jobs_table         = $wpdb->prefix . 'recruitech_jobs';
	$job_seekers_table  = $wpdb->prefix . 'recruitech_job_seekers';
	$users_table        = $wpdb->users;

	// Step 1 & 2: pull the application together with its job and candidate profile.
	// The ownership check (j.company_id = %d) makes sure a company can only ever
	// analyze applications for its own jobs.
	$record = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT a.id AS application_id, a.job_id, a.job_seeker_id AS applicant_user_id,
				a.ai_feedback, a.ai_input_hash,
				j.company_id, j.job_title, j.description AS job_description, j.requirements AS job_requirements,
				j.required_skills, j.experience_level, j.job_category,
				js.id AS job_seeker_profile_id, js.full_name, js.summary AS candidate_summary,
				js.skills AS candidate_skills, js.experience AS candidate_experience,
				u.user_email
			FROM {$applications_table} AS a
			INNER JOIN {$jobs_table} AS j ON a.job_id = j.id
			INNER JOIN {$job_seekers_table} AS js ON a.job_seeker_id = js.user_id
			LEFT JOIN {$users_table} AS u ON js.user_id = u.ID
			WHERE a.id = %d AND j.company_id = %d
			LIMIT 1",
			$application_id,
			$company_id
		),
		ARRAY_A
	);

	if ( empty( $record ) ) {
		recruittech_ai_insert_analysis_log( $wpdb, $application_id, 'application not found', 'application not found' );
		return new WP_Error( 'recruittech_ai_not_found', 'Application not found for this company.' );
	}

	$candidate_cv_text  = recruittech_ai_get_candidate_cv_text( absint( $record['job_seeker_profile_id'] ) );
	$all_documents_text = recruittech_ai_get_company_documents_all_text( absint( $record['company_id'] ) );

	// The cache key covers everything that could change the answer: the job
	// fields, the candidate's CV text, and every company document's text. If
	// the recruiter edits the job, the candidate re-uploads their CV, or the
	// company adds/removes/edits a hiring document, the hash changes and a
	// fresh analysis runs; otherwise the last saved result is reused and the
	// AI gateway isn't called again.
	$current_hash = recruittech_ai_compute_hash(
		array(
			$record['job_title'],
			$record['job_category'],
			$record['experience_level'],
			$record['required_skills'],
			$record['job_description'],
			$record['job_requirements'],
			isset( $record['qualifications'] ) ? $record['qualifications'] : '',
			$record['candidate_summary'],
			$record['candidate_skills'],
			$record['candidate_experience'],
			$candidate_cv_text,
			$all_documents_text,
		)
	);

	if ( ! $force_refresh && ! empty( $record['ai_input_hash'] ) && ! empty( $record['ai_feedback'] ) && hash_equals( $record['ai_input_hash'], $current_hash ) ) {
		$cached_analysis = json_decode( $record['ai_feedback'], true );
		if ( is_array( $cached_analysis ) ) {
			recruittech_ai_insert_analysis_log( $wpdb, $application_id, '(served from cache)', '(served from cache)' );
			$cached_analysis['application_id'] = $application_id;
			$cached_analysis['candidate_name'] = $record['full_name'];
			$cached_analysis['job_title']      = $record['job_title'];
			$cached_analysis['saved']          = true;
			$cached_analysis['from_cache']     = true;
			return $cached_analysis;
		}
	}

	// Step 3: lightweight RAG over the company's own recruitment documents.
	$retrieval_query = implode(
		' ',
		array(
			$record['job_title'],
			$record['job_requirements'],
			$record['required_skills'],
			$record['candidate_skills'],
			$record['candidate_summary'],
		)
	);
	$policy_context = recruittech_ai_get_company_documents_context( absint( $record['company_id'] ), $retrieval_query );

	// Step 4: build the prompt.
	$prompt = recruittech_ai_build_analysis_prompt( $record, $candidate_cv_text, $policy_context );

	// Step 5: send it to the LLM.
	$ai_response = RecruitTech_AI_Client::chat( $prompt['system'], $prompt['user'] );

	// Log every attempt (success or failure) so there is a full audit trail,
	// per the ai_analysis_log table in the project's data model.
	recruittech_ai_insert_analysis_log(
		$wpdb,
		$application_id,
		substr( $prompt['system'] . "\n\n" . $prompt['user'], 0, 20000 ),
		is_wp_error( $ai_response ) ? ( 'ERROR: ' . $ai_response->get_error_message() ) : substr( (string) $ai_response, 0, 20000 )
	);

	if ( is_wp_error( $ai_response ) ) {
		return $ai_response;
	}

	// Step 6: parse the model's reply and save the analysis, along with the
	// input hash so the next request can tell whether anything changed.
	$analysis = recruittech_ai_parse_analysis_response( (string) $ai_response );

	$applications_update = $wpdb->update(
		$applications_table,
		array(
			'match_score'    => is_numeric( $analysis['match_score'] ) ? (string) absint( $analysis['match_score'] ) : '',
			'ai_feedback'    => wp_json_encode( $analysis ),
			'ai_input_hash'  => $current_hash,
		),
		array( 'id' => $application_id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);

	$questions_table = $wpdb->prefix . 'recruitech_ai_interview_questions';
	$wpdb->delete( $questions_table, array( 'application_id' => $application_id ), array( '%d' ) );
	foreach ( $analysis['interview_questions'] as $question ) {
		$question = trim( (string) $question );
		if ( '' === $question ) {
			continue;
		}
		$wpdb->insert(
			$questions_table,
			array(
				'application_id' => $application_id,
				'question_text'  => $question,
			),
			array( '%d', '%s' )
		);
	}

	// Step 7: return the final hiring report to the recruiter.
	$analysis['application_id'] = $application_id;
	$analysis['candidate_name'] = $record['full_name'];
	$analysis['job_title']      = $record['job_title'];
	$analysis['saved']          = ( false !== $applications_update );
	$analysis['from_cache']     = false;

	return $analysis;
}

/**
 * Build a stable cache key (sha256) from a list of input strings. A rare
 * separator character is used between parts so that, e.g., ("ab","c") and
 * ("a","bc") never hash to the same value.
 *
 * @param array $parts Ordered list of input strings.
 * @return string
 */
function recruittech_ai_compute_hash( $parts ) {
	$normalized = array_map(
		function ( $part ) {
			return trim( (string) $part );
		},
		(array) $parts
	);

	return hash( 'sha256', implode( "\x1F", $normalized ) );
}

/**
 * Get the concatenated text of every one of a company's uploaded documents,
 * used only to build the cache hash above (so adding, removing, or the
 * content of any document changes the hash, even ones not picked by RAG).
 *
 * @param int $company_id Company ID.
 * @return string
 */
function recruittech_ai_get_company_documents_all_text( $company_id ) {
	global $wpdb;

	$company_id = absint( $company_id );
	if ( ! $company_id ) {
		return '';
	}

	$documents_table = $wpdb->prefix . 'recruitech_company_documents';
	$documents = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, file_path, extracted_text FROM {$documents_table} WHERE company_id = %d ORDER BY id ASC",
			$company_id
		),
		ARRAY_A
	);

	if ( empty( $documents ) ) {
		return '';
	}

	$parts = array();
	foreach ( $documents as $document ) {
		$text = $document['extracted_text'];

		if ( empty( $text ) && ! empty( $document['file_path'] ) && class_exists( 'RecruitTech_Text_Extractor' ) ) {
			$text = RecruitTech_Text_Extractor::extract_from_url( $document['file_path'] );
			$text = substr( $text, 0, 6000 );

			if ( '' !== $text ) {
				$wpdb->update(
					$documents_table,
					array( 'extracted_text' => $text ),
					array( 'id' => absint( $document['id'] ) ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		$parts[] = $document['id'] . ':' . $text;
	}

	return implode( '|', $parts );
}

/**
 * Analyze every applicant for a single job and return the top N by AI match
 * score, so a recruiter facing a large number of applications can quickly
 * shortlist the strongest candidates instead of reviewing everyone by hand.
 *
 * Applications whose inputs haven't changed since their last analysis reuse
 * the cached result (see recruittech_ai_analyze_application()), so re-running
 * this after the first time is fast.
 *
 * @param int $job_id     Job ID.
 * @param int $company_id Company ID (ownership check).
 * @param int $limit      How many top candidates to return.
 * @return array|WP_Error array{ranked: array, analyzed: int, skipped: int, failed: int}
 */
function recruittech_ai_rank_top_candidates( $job_id, $company_id, $limit = 10 ) {
	global $wpdb;

	$job_id     = absint( $job_id );
	$company_id = absint( $company_id );
	$limit      = max( 1, absint( $limit ) );

	if ( ! $job_id || ! $company_id ) {
		return new WP_Error( 'recruittech_ai_invalid_request', 'Invalid job.' );
	}

	$jobs_table = $wpdb->prefix . 'recruitech_jobs';
	$job_owned  = $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$jobs_table} WHERE id = %d AND company_id = %d LIMIT 1", $job_id, $company_id )
	);

	if ( ! $job_owned ) {
		return new WP_Error( 'recruittech_ai_not_found', 'Job not found for this company.' );
	}

	$applications_table = $wpdb->prefix . 'recruitech_applications';
	$application_ids    = $wpdb->get_col(
		$wpdb->prepare( "SELECT id FROM {$applications_table} WHERE job_id = %d ORDER BY created_at ASC", $job_id )
	);

	$ranked  = array();
	$skipped = 0;
	$failed  = 0;

	// Large applicant pools can take a while since each uncached candidate is
	// a separate AI call; give this request more room than the default limit.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 );
	}

	foreach ( $application_ids as $application_id ) {
		$result = recruittech_ai_analyze_application( absint( $application_id ), $company_id );

		if ( is_wp_error( $result ) ) {
			$failed++;
			continue;
		}

		if ( null === $result['match_score'] ) {
			$skipped++;
			continue;
		}

		$ranked[] = $result;
	}

	usort(
		$ranked,
		function ( $a, $b ) {
			return $b['match_score'] <=> $a['match_score'];
		}
	);

	return array(
		'ranked'   => array_slice( $ranked, 0, $limit ),
		'analyzed' => count( $ranked ),
		'skipped'  => $skipped,
		'failed'   => $failed,
		'total'    => count( $application_ids ),
	);
}

/**
 * Get (and lazily cache) the extracted text of a candidate's most recent CV.
 *
 * @param int $job_seeker_profile_id job_seekers.id (not the WP user ID).
 * @return string
 */
function recruittech_ai_get_candidate_cv_text( $job_seeker_profile_id ) {
	global $wpdb;

	$job_seeker_profile_id = absint( $job_seeker_profile_id );
	if ( ! $job_seeker_profile_id ) {
		return '';
	}

	$cv_table = $wpdb->prefix . 'recruitech_cvs';
	$cv = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, file_path, extracted_text FROM {$cv_table} WHERE job_seeker_id = %d ORDER BY uploaded_at DESC, id DESC LIMIT 1",
			$job_seeker_profile_id
		),
		ARRAY_A
	);

	if ( empty( $cv ) ) {
		return '';
	}

	if ( ! empty( $cv['extracted_text'] ) ) {
		return $cv['extracted_text'];
	}

	if ( empty( $cv['file_path'] ) || ! class_exists( 'RecruitTech_Text_Extractor' ) ) {
		return '';
	}

	$extracted_text = RecruitTech_Text_Extractor::extract_from_url( $cv['file_path'] );

	if ( '' !== $extracted_text ) {
		$wpdb->update(
			$cv_table,
			array( 'extracted_text' => $extracted_text ),
			array( 'id' => absint( $cv['id'] ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	return $extracted_text;
}

/**
 * Lightweight RAG: retrieve the company documents most relevant to the
 * current job/candidate, without needing embeddings or a vector database.
 *
 * Each document is scored by how many significant words it shares with the
 * query text (job requirements + candidate skills). The highest-scoring
 * documents are concatenated (bounded by a character budget) so the AI only
 * sees the company's own hiring policies that are actually relevant to this
 * candidate, rather than every uploaded document.
 *
 * @param int    $company_id Company ID.
 * @param string $query_text Text describing what this analysis is about.
 * @return string
 */
function recruittech_ai_get_company_documents_context( $company_id, $query_text ) {
	global $wpdb;

	$company_id = absint( $company_id );
	if ( ! $company_id ) {
		return '';
	}

	$documents_table = $wpdb->prefix . 'recruitech_company_documents';
	$documents = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, file_path, doc_type, extracted_text FROM {$documents_table} WHERE company_id = %d ORDER BY uploaded_at DESC",
			$company_id
		),
		ARRAY_A
	);

	if ( empty( $documents ) ) {
		return '';
	}

	$query_keywords = recruittech_ai_extract_keywords( $query_text );

	$scored_documents = array();
	foreach ( $documents as $document ) {
		$text = $document['extracted_text'];

		if ( empty( $text ) && ! empty( $document['file_path'] ) && class_exists( 'RecruitTech_Text_Extractor' ) ) {
			$text = RecruitTech_Text_Extractor::extract_from_url( $document['file_path'] );
			$text = substr( $text, 0, 6000 );

			if ( '' !== $text ) {
				$wpdb->update(
					$documents_table,
					array( 'extracted_text' => $text ),
					array( 'id' => absint( $document['id'] ) ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		if ( empty( $text ) ) {
			continue;
		}

		$document_keywords = recruittech_ai_extract_keywords( $text );
		$overlap_score      = count( array_intersect( $query_keywords, $document_keywords ) );

		$scored_documents[] = array(
			'doc_type' => $document['doc_type'],
			'text'     => $text,
			'score'    => $overlap_score,
		);
	}

	if ( empty( $scored_documents ) ) {
		return '';
	}

	usort(
		$scored_documents,
		function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);

	$character_budget = 6000;
	$context_parts    = array();

	foreach ( $scored_documents as $document ) {
		if ( $character_budget <= 0 ) {
			break;
		}

		$label   = ! empty( $document['doc_type'] ) ? $document['doc_type'] : 'Company Document';
		$chunk   = substr( $document['text'], 0, min( 2500, $character_budget ) );
		$context_parts[] = "[{$label}]\n{$chunk}";
		$character_budget -= strlen( $chunk );
	}

	return implode( "\n\n", $context_parts );
}

/**
 * Turn a block of text into a lowercase set of significant words, used for
 * the simple keyword-overlap retrieval above.
 *
 * @param string $text Input text.
 * @return array
 */
function recruittech_ai_extract_keywords( $text ) {
	static $stopwords = array(
		'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with',
		'is', 'are', 'be', 'as', 'at', 'by', 'this', 'that', 'it', 'from',
		'will', 'we', 'you', 'your', 'our', 'their', 'has', 'have', 'was',
		'were', 'not', 'but', 'all', 'any', 'can', 'must', 'should',
	);

	$text  = strtolower( (string) $text );
	$words = preg_split( '/[^a-z0-9\+\#]+/', $text );
	$words = array_filter(
		$words,
		function ( $word ) use ( $stopwords ) {
			return strlen( $word ) > 2 && ! in_array( $word, $stopwords, true );
		}
	);

	return array_unique( $words );
}

/**
 * Build the system and user prompts sent to the LLM.
 *
 * @param array  $record            Application/job/candidate data (see recruittech_ai_analyze_application()).
 * @param string $candidate_cv_text Extracted CV text.
 * @param string $policy_context    Retrieved company document context (RAG).
 * @return array{system:string,user:string}
 */
function recruittech_ai_build_analysis_prompt( $record, $candidate_cv_text, $policy_context ) {
	$system_prompt = "You are an AI recruitment assistant helping an HR recruiter evaluate a job candidate. "
		. "Base your evaluation on both the candidate's profile data and the extracted CV text, and use the job requirements as the main decision criteria. "
		. "Do not rely only on the profile fields; the CV text often contains important details that are not stored in the profile. "
		. "Company hiring policies always take priority over generic best practices. "
		. "Respond with ONLY a single valid JSON object (no markdown, no extra commentary) using exactly this shape:\n"
		. '{"match_score": <integer 0-100>, "summary": "<2-4 sentence overview of the candidate>", '
		. '"strengths": ["..."], "gaps": ["..."], "interview_questions": ["..." (3-5 tailored questions)], '
		. '"recommendation": "Strongly Recommend" | "Recommend" | "Consider" | "Not Recommended"}';

	$user_message  = "JOB TITLE: " . $record['job_title'] . "\n";
	$user_message .= "JOB CATEGORY: " . $record['job_category'] . "\n";
	$user_message .= "EXPERIENCE LEVEL REQUIRED: " . $record['experience_level'] . "\n";
	$user_message .= "REQUIRED SKILLS: " . $record['required_skills'] . "\n";
	$user_message .= "JOB DESCRIPTION: " . $record['job_description'] . "\n";
	$user_message .= "JOB REQUIREMENTS: " . $record['job_requirements'] . "\n\n";

	$user_message .= "CANDIDATE NAME: " . $record['full_name'] . "\n";
	$user_message .= "CANDIDATE PROFILE DATA (from the profile form):\n";
	$user_message .= "SUMMARY: " . $record['candidate_summary'] . "\n";
	$user_message .= "SKILLS: " . $record['candidate_skills'] . "\n";
	$user_message .= "EXPERIENCE: " . $record['candidate_experience'] . "\n";

	$optional_profile_fields = array(
		'candidate_education'       => 'EDUCATION',
		'candidate_certifications'  => 'CERTIFICATIONS',
		'candidate_languages'       => 'LANGUAGES',
		'candidate_job_title'       => 'JOB TITLE',
		'candidate_years_experience'=> 'YEARS OF EXPERIENCE',
		'candidate_location'        => 'LOCATION',
		'candidate_preferred_job'   => 'PREFERRED JOB',
	);

	foreach ( $optional_profile_fields as $field_key => $field_label ) {
		if ( isset( $record[ $field_key ] ) && '' !== trim( (string) $record[ $field_key ] ) ) {
			$user_message .= $field_label . ': ' . trim( (string) $record[ $field_key ] ) . "\n";
		}
	}

	$user_message .= "\n";
	$user_message .= "EXTRACTED CV TEXT FROM extracted_text FIELD:\n";
	$user_message .= ( '' !== $candidate_cv_text ) ? substr( $candidate_cv_text, 0, 6000 ) : '(No CV text could be extracted. Base the evaluation on both the profile data and the CV text above.)';
	$user_message .= "\n\n";

	$user_message .= "COMPANY HIRING POLICIES / RECRUITMENT DOCUMENTS (use these to judge fit, if relevant):\n";
	$user_message .= ( '' !== $policy_context ) ? $policy_context : '(The company has not uploaded any hiring documents yet. Use general best practices.)';

	return array(
		'system' => $system_prompt,
		'user'   => $user_message,
	);
}

/**
 * Parse the LLM's reply into a normalized analysis array, tolerating a
 * model that wraps its JSON in a markdown code fence or adds stray text.
 *
 * @param string $raw_text Raw text returned by RecruitTech_AI_Client::chat().
 * @return array
 */
function recruittech_ai_parse_analysis_response( $raw_text ) {
	$default = array(
		'match_score'          => null,
		'summary'              => '',
		'strengths'            => array(),
		'gaps'                 => array(),
		'interview_questions'  => array(),
		'recommendation'       => '',
	);

	$cleaned = recruittech_ai_extract_json_object( $raw_text );
	$decoded = json_decode( $cleaned, true );

	if ( ! is_array( $decoded ) ) {
		$default['summary'] = wp_strip_all_tags( substr( $raw_text, 0, 1000 ) );
		return $default;
	}

	$match_score = isset( $decoded['match_score'] ) && is_numeric( $decoded['match_score'] )
		? max( 0, min( 100, (int) $decoded['match_score'] ) )
		: null;

	return array(
		'match_score'         => $match_score,
		'summary'             => isset( $decoded['summary'] ) ? sanitize_textarea_field( $decoded['summary'] ) : '',
		'strengths'           => isset( $decoded['strengths'] ) && is_array( $decoded['strengths'] ) ? array_map( 'sanitize_text_field', $decoded['strengths'] ) : array(),
		'gaps'                => isset( $decoded['gaps'] ) && is_array( $decoded['gaps'] ) ? array_map( 'sanitize_text_field', $decoded['gaps'] ) : array(),
		'interview_questions' => isset( $decoded['interview_questions'] ) && is_array( $decoded['interview_questions'] ) ? array_map( 'sanitize_text_field', $decoded['interview_questions'] ) : array(),
		'recommendation'      => isset( $decoded['recommendation'] ) ? sanitize_text_field( $decoded['recommendation'] ) : '',
	);
}

/**
 * Strip a markdown code fence (```json ... ```) and any stray text around a
 * JSON object, so json_decode() gets a clean string. Shared by both the
 * recruiter-side analysis parser and the job-seeker fit-check parser.
 *
 * @param string $raw_text Raw text returned by the LLM.
 * @return string
 */
function recruittech_ai_extract_json_object( $raw_text ) {
	$cleaned = trim( (string) $raw_text );
	$cleaned = preg_replace( '/^```(?:json)?/i', '', $cleaned );
	$cleaned = preg_replace( '/```$/', '', $cleaned );
	$cleaned = trim( $cleaned );

	if ( '' !== $cleaned && '{' !== $cleaned[0] ) {
		$start = strpos( $cleaned, '{' );
		$end   = strrpos( $cleaned, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$cleaned = substr( $cleaned, $start, $end - $start + 1 );
		}
	}

	return $cleaned;
}

/**
 * "Check My Fit": lets a job seeker see, before applying, how well their CV
 * matches a job and what to improve. Deliberately does NOT use the
 * company's internal hiring documents (RAG) — those are for the recruiter's
 * eyes only, not for candidates to see or reverse-engineer.
 *
 * Results are cached per (job, job_seeker) pair in job_fit_checks and reused
 * as long as neither the job nor the candidate's CV has changed since.
 *
 * @param int  $job_id           Job ID.
 * @param int  $job_seeker_user_id WP user ID of the job seeker (current user).
 * @param bool $force_refresh    Bypass the cache.
 * @return array|WP_Error
 */
function recruittech_ai_check_job_fit( $job_id, $job_seeker_user_id, $force_refresh = false ) {
	global $wpdb;

	$job_id             = absint( $job_id );
	$job_seeker_user_id = absint( $job_seeker_user_id );

	if ( ! $job_id || ! $job_seeker_user_id ) {
		return new WP_Error( 'recruittech_ai_invalid_request', 'Invalid request.' );
	}

	$jobs_table        = $wpdb->prefix . 'recruitech_jobs';
	$job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';

	$job = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, job_title, job_category, experience_level, required_skills, description, requirements, status FROM {$jobs_table} WHERE id = %d LIMIT 1", $job_id ),
		ARRAY_A
	);

	if ( empty( $job ) ) {
		return new WP_Error( 'recruittech_ai_not_found', 'Job not found.' );
	}

	$job_seeker = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, full_name, summary, skills, experience FROM {$job_seekers_table} WHERE user_id = %d LIMIT 1", $job_seeker_user_id ),
		ARRAY_A
	);

	if ( empty( $job_seeker ) ) {
		return new WP_Error( 'recruittech_ai_not_found', 'Job seeker profile not found.' );
	}

	$candidate_cv_text = recruittech_ai_get_candidate_cv_text( absint( $job_seeker['id'] ) );

	if ( '' === $candidate_cv_text ) {
		return new WP_Error( 'recruittech_ai_no_cv', 'Please upload your CV to your profile before checking your fit for a job.' );
	}

	$current_hash = recruittech_ai_compute_hash(
		array(
			$job['job_title'],
			$job['job_category'],
			$job['experience_level'],
			$job['required_skills'],
			$job['description'],
			$job['requirements'],
			isset( $job['qualifications'] ) ? $job['qualifications'] : '',
			$job_seeker['summary'],
			$job_seeker['skills'],
			$job_seeker['experience'],
			$candidate_cv_text,
		)
	);

	$fit_checks_table = $wpdb->prefix . 'recruitech_job_fit_checks';
	$cached_row        = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT input_hash, analysis_json FROM {$fit_checks_table} WHERE job_id = %d AND job_seeker_id = %d LIMIT 1",
			$job_id,
			absint( $job_seeker['id'] )
		),
		ARRAY_A
	);

	if ( ! $force_refresh && ! empty( $cached_row['input_hash'] ) && hash_equals( $cached_row['input_hash'], $current_hash ) ) {
		$cached_analysis = json_decode( $cached_row['analysis_json'], true );
		if ( is_array( $cached_analysis ) ) {
			$cached_analysis['job_title']  = $job['job_title'];
			$cached_analysis['from_cache'] = true;
			return $cached_analysis;
		}
	}

	$prompt = recruittech_ai_build_fit_check_prompt( $job, $job_seeker, $candidate_cv_text );

	$ai_response = RecruitTech_AI_Client::chat( $prompt['system'], $prompt['user'] );

	if ( is_wp_error( $ai_response ) ) {
		return $ai_response;
	}

	$analysis = recruittech_ai_parse_fit_response( (string) $ai_response );

	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$fit_checks_table} (job_id, job_seeker_id, input_hash, match_score, analysis_json)
				VALUES (%d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE input_hash = VALUES(input_hash), match_score = VALUES(match_score), analysis_json = VALUES(analysis_json), updated_at = CURRENT_TIMESTAMP",
			$job_id,
			absint( $job_seeker['id'] ),
			$current_hash,
			is_numeric( $analysis['match_score'] ) ? absint( $analysis['match_score'] ) : null,
			wp_json_encode( $analysis )
		)
	);

	$analysis['job_title']  = $job['job_title'];
	$analysis['from_cache'] = false;

	return $analysis;
}

/**
 * Build the prompt for the pre-application "Check My Fit" feature.
 * Only the job and the candidate's own CV are used — no company internal
 * documents, since the candidate should never see those.
 *
 * @param array  $job                Job row.
 * @param array  $job_seeker         Job seeker profile row.
 * @param string $candidate_cv_text  Extracted CV text.
 * @return array{system:string,user:string}
 */
function recruittech_ai_build_fit_check_prompt( $job, $job_seeker, $candidate_cv_text ) {
	$system_prompt = "You are a friendly career coach helping a job seeker decide whether to apply for a job, and how to improve their CV first. "
		. "Judge fit using ONLY the job details and the candidate's CV provided below. Be honest but encouraging. "
		. "Respond with ONLY a single valid JSON object (no markdown, no extra commentary) using exactly this shape:\n"
		. '{"match_score": <integer 0-100>, "summary": "<2-3 sentence honest assessment>", '
		. '"matching_skills": ["skills/experience from the CV that fit this job"], '
		. '"missing_skills": ["skills/qualifications the job wants that the CV does not show"], '
		. '"cv_improvement_tips": ["3-5 concrete, actionable tips to improve the CV specifically for THIS job before applying"]}';

	$user_message  = "JOB TITLE: " . $job['job_title'] . "\n";
	$user_message .= "JOB CATEGORY: " . $job['job_category'] . "\n";
	$user_message .= "EXPERIENCE LEVEL REQUIRED: " . $job['experience_level'] . "\n";
	$user_message .= "REQUIRED SKILLS: " . $job['required_skills'] . "\n";
	$user_message .= "JOB DESCRIPTION: " . $job['description'] . "\n";
	$user_message .= "JOB REQUIREMENTS: " . $job['requirements'] . "\n\n";

	$user_message .= "CANDIDATE PROFILE DATA:\n";
	$user_message .= "PROFILE SUMMARY: " . $job_seeker['summary'] . "\n";
	$user_message .= "LISTED SKILLS: " . $job_seeker['skills'] . "\n";
	$user_message .= "LISTED EXPERIENCE: " . $job_seeker['experience'] . "\n";

	$optional_fit_profile_fields = array(
		'education'       => 'EDUCATION',
		'certifications'  => 'CERTIFICATIONS',
		'languages'       => 'LANGUAGES',
		'job_title'       => 'JOB TITLE',
		'years_of_experience' => 'YEARS OF EXPERIENCE',
		'location'        => 'LOCATION',
		'preferred_job'   => 'PREFERRED JOB',
	);

	foreach ( $optional_fit_profile_fields as $field_key => $field_label ) {
		if ( isset( $job_seeker[ $field_key ] ) && '' !== trim( (string) $job_seeker[ $field_key ] ) ) {
			$user_message .= $field_label . ': ' . trim( (string) $job_seeker[ $field_key ] ) . "\n";
		}
	}

	$user_message .= "\n";
	$user_message .= "EXTRACTED CV TEXT FROM extracted_text FIELD:\n" . substr( $candidate_cv_text, 0, 6000 );

	return array(
		'system' => $system_prompt,
		'user'   => $user_message,
	);
}

/**
 * Parse the LLM's reply for the "Check My Fit" feature.
 *
 * @param string $raw_text Raw text returned by RecruitTech_AI_Client::chat().
 * @return array
 */
function recruittech_ai_parse_fit_response( $raw_text ) {
	$default = array(
		'match_score'         => null,
		'summary'             => '',
		'matching_skills'     => array(),
		'missing_skills'      => array(),
		'cv_improvement_tips' => array(),
	);

	$cleaned = recruittech_ai_extract_json_object( $raw_text );
	$decoded = json_decode( $cleaned, true );

	if ( ! is_array( $decoded ) ) {
		$default['summary'] = wp_strip_all_tags( substr( $raw_text, 0, 1000 ) );
		return $default;
	}

	$match_score = isset( $decoded['match_score'] ) && is_numeric( $decoded['match_score'] )
		? max( 0, min( 100, (int) $decoded['match_score'] ) )
		: null;

	return array(
		'match_score'         => $match_score,
		'summary'             => isset( $decoded['summary'] ) ? sanitize_textarea_field( $decoded['summary'] ) : '',
		'matching_skills'     => isset( $decoded['matching_skills'] ) && is_array( $decoded['matching_skills'] ) ? array_map( 'sanitize_text_field', $decoded['matching_skills'] ) : array(),
		'missing_skills'      => isset( $decoded['missing_skills'] ) && is_array( $decoded['missing_skills'] ) ? array_map( 'sanitize_text_field', $decoded['missing_skills'] ) : array(),
		'cv_improvement_tips' => isset( $decoded['cv_improvement_tips'] ) && is_array( $decoded['cv_improvement_tips'] ) ? array_map( 'sanitize_text_field', $decoded['cv_improvement_tips'] ) : array(),
	);
}
