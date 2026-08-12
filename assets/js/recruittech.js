// RecruitTech frontend script

/**
 * AI Recruitment Assistant: "Analyze with AI" button on Company Applications.
 * Calls the recruittech_analyze_candidate AJAX action (includes/ai-ajax.php),
 * which runs the AI Agent (includes/ai/class-agent.php) and returns a
 * structured hiring report, then renders it inside the Bootstrap modal.
 */
(function () {
	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function renderList(items) {
		if (!items || !items.length) {
			return '<p class="text-muted mb-0">None noted.</p>';
		}
		var html = '<ul class="mb-0">';
		items.forEach(function (item) {
			html += '<li>' + escapeHtml(item) + '</li>';
		});
		return html + '</ul>';
	}

	function recommendationBadgeClass(recommendation) {
		switch (recommendation) {
			case 'Strongly Recommend':
				return 'bg-success';
			case 'Recommend':
				return 'bg-primary';
			case 'Consider':
				return 'bg-warning text-dark';
			case 'Not Recommended':
				return 'bg-danger';
			default:
				return 'bg-secondary';
		}
	}

	function scoreRingClass(score) {
		var numericScore = parseInt(score, 10);
		if (isNaN(numericScore)) {
			return 'rt-score-unknown';
		}
		if (numericScore >= 80) {
			return 'rt-score-high';
		}
		if (numericScore >= 60) {
			return 'rt-score-medium';
		}
		if (numericScore >= 40) {
			return 'rt-score-low';
		}
		return 'rt-score-poor';
	}

	function renderScoreRing(score) {
		var hasScore = !(score === null || typeof score === 'undefined' || score === '');
		var displayValue = hasScore ? escapeHtml(score) : '\u2014';
		return '<div class="rt-score-ring ' + scoreRingClass(score) + '">'
			+ '<span class="rt-score-ring-value">' + displayValue + '</span>'
			+ '<span class="rt-score-ring-label">' + (hasScore ? '/ 100' : 'N/A') + '</span>'
			+ '</div>';
	}

	function renderReport(report) {
		var html = '<div class="rt-ai-report">';

		html += '<div class="rt-ai-report-header">';
		html += renderScoreRing(report.match_score);
		html += '<div class="rt-ai-report-heading">';
		html += '<h5 class="mb-1">' + escapeHtml(report.candidate_name || '') + '</h5>';
		html += '<p class="text-muted mb-2">' + escapeHtml(report.job_title || '') + '</p>';
		if (report.recommendation) {
			html += '<span class="badge ' + recommendationBadgeClass(report.recommendation) + '">' + escapeHtml(report.recommendation) + '</span>';
		}
		if (report.from_cache) {
			html += '<div class="text-muted small mt-2"><i class="bi bi-clock-history"></i> Cached result (nothing changed since last analysis)</div>';
		}
		html += '</div>';
		html += '</div>';

		html += '<div class="rt-ai-section">';
		html += '<h6 class="rt-ai-section-title"><i class="bi bi-file-text"></i> Summary</h6>';
		html += '<p class="mb-0">' + escapeHtml(report.summary || 'No summary provided.') + '</p>';
		html += '</div>';

		html += '<div class="row g-3">';
		html += '<div class="col-md-6"><div class="rt-ai-section rt-ai-section-good h-100">'
			+ '<h6 class="rt-ai-section-title"><i class="bi bi-check-circle"></i> Strengths</h6>' + renderList(report.strengths) + '</div></div>';
		html += '<div class="col-md-6"><div class="rt-ai-section rt-ai-section-bad h-100">'
			+ '<h6 class="rt-ai-section-title"><i class="bi bi-exclamation-circle"></i> Gaps</h6>' + renderList(report.gaps) + '</div></div>';
		html += '</div>';

		html += '<div class="rt-ai-section mt-3">';
		html += '<h6 class="rt-ai-section-title"><i class="bi bi-chat-left-question"></i> Suggested Interview Questions</h6>' + renderList(report.interview_questions);
		html += '</div>';

		html += '</div>';

		return html;
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.rt-ai-analyze-btn');
		if (!button || typeof recruittechAjax === 'undefined') {
			return;
		}

		var applicationId = button.getAttribute('data-application-id');
		var candidateName = button.getAttribute('data-candidate-name') || '';
		var modalEl = document.getElementById('rtAiAnalysisModal');
		var modalBody = document.getElementById('rtAiAnalysisModalBody');
		if (!modalEl || !modalBody) {
			return;
		}

		modalBody.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted">'
			+ '<div class="spinner-border spinner-border-sm" role="status"></div>'
			+ '<span>Analyzing ' + escapeHtml(candidateName) + '\u2019s CV against the job and your hiring documents&hellip;</span></div>';

		var modal = (window.bootstrap && window.bootstrap.Modal)
			? window.bootstrap.Modal.getOrCreateInstance(modalEl)
			: null;
		if (modal) {
			modal.show();
		}

		var formData = new FormData();
		formData.append('action', 'recruittech_analyze_candidate');
		formData.append('nonce', recruittechAjax.analyzeNonce);
		formData.append('application_id', applicationId);

		fetch(recruittechAjax.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (payload && payload.success) {
					modalBody.innerHTML = renderReport(payload.data);
				} else {
					var message = (payload && payload.data && payload.data.message) ? payload.data.message : 'Something went wrong while analyzing this candidate.';
					modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>';
				}
			})
			.catch(function () {
				modalBody.innerHTML = '<div class="alert alert-danger mb-0">Could not reach the AI Assistant. Please try again.</div>';
			});
	});
	function renderRankResults(job_id, payload) {
		var ranked = payload.ranked || [];
		var html = '<div class="rt-rank-summary d-flex flex-wrap align-items-center gap-2 mb-3">';
		html += '<span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-people"></i> ' + escapeHtml(payload.total) + ' applicant(s)</span>';
		html += '<span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> ' + escapeHtml(payload.analyzed) + ' analyzed</span>';
		if (payload.skipped) {
			html += '<span class="badge bg-secondary-subtle text-secondary-emphasis">' + escapeHtml(payload.skipped) + ' skipped (no CV/score)</span>';
		}
		if (payload.failed) {
			html += '<span class="badge bg-danger-subtle text-danger-emphasis">' + escapeHtml(payload.failed) + ' failed</span>';
		}
		html += '</div>';

		if (!ranked.length) {
			return html + '<p class="mb-0 text-muted">No candidates could be scored yet. Make sure applicants have uploaded a CV.</p>';
		}

		html += '<div class="accordion rt-rank-accordion" id="rtRankAccordion">';
		ranked.forEach(function (candidate, index) {
			var collapseId = 'rtRankItem' + index;
			var scoreLabel = (candidate.match_score === null || typeof candidate.match_score === 'undefined') ? 'N/A' : candidate.match_score;
			var rankBadgeClass = index === 0 ? 'rt-rank-badge rt-rank-badge-first' : (index < 3 ? 'rt-rank-badge rt-rank-badge-top' : 'rt-rank-badge');
			html += '<div class="accordion-item rt-rank-item">';
			html += '<h2 class="accordion-header">';
			html += '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '">';
			html += '<span class="' + rankBadgeClass + '">' + (index === 0 ? '<i class="bi bi-trophy-fill"></i>' : '#' + (index + 1)) + '</span>';
			html += '<span class="fw-semibold rt-rank-name">' + escapeHtml(candidate.candidate_name || '') + '</span>';
			html += '<span class="badge ' + recommendationBadgeClass(candidate.recommendation) + ' ms-auto me-2">' + escapeHtml(candidate.recommendation || '') + '</span>';
			html += '<span class="rt-rank-score">' + escapeHtml(scoreLabel) + '<small>/100</small></span>';
			html += '</button></h2>';
			html += '<div id="' + collapseId + '" class="accordion-collapse collapse" data-bs-parent="#rtRankAccordion">';
			html += '<div class="accordion-body">' + renderReport(candidate) + '</div></div></div>';
		});
		html += '</div>';

		return html;
	}

	document.addEventListener('click', function (event) {
		var rankButton = event.target.closest('.rt-ai-rank-btn');
		if (!rankButton || typeof recruittechAjax === 'undefined') {
			return;
		}

		var jobId = rankButton.getAttribute('data-job-id');
		var modalEl = document.getElementById('rtAiRankModal');
		var modalBody = document.getElementById('rtAiRankModalBody');
		if (!modalEl || !modalBody) {
			return;
		}

		modalBody.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted">'
			+ '<div class="spinner-border spinner-border-sm" role="status"></div>'
			+ '<span>Analyzing every applicant for this job&hellip; this can take a moment for large applicant pools.</span></div>';

		var modal = (window.bootstrap && window.bootstrap.Modal)
			? window.bootstrap.Modal.getOrCreateInstance(modalEl)
			: null;
		if (modal) {
			modal.show();
		}

		var formData = new FormData();
		formData.append('action', 'recruittech_rank_top_candidates');
		formData.append('nonce', recruittechAjax.analyzeNonce);
		formData.append('job_id', jobId);

		fetch(recruittechAjax.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (payload && payload.success) {
					modalBody.innerHTML = renderRankResults(jobId, payload.data);
				} else {
					var message = (payload && payload.data && payload.data.message) ? payload.data.message : 'Something went wrong while ranking candidates.';
					modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>';
				}
			})
			.catch(function () {
				modalBody.innerHTML = '<div class="alert alert-danger mb-0">Could not reach the AI Assistant. Please try again.</div>';
			});
	});
	function renderFitReport(report) {
		var html = '<div class="card rt-fit-card border-0 shadow-sm">';
		html += '<div class="card-body">';
		html += '<div class="rt-ai-report-header rt-fit-report-header">';
		html += renderScoreRing(report.match_score);
		html += '<div class="rt-ai-report-heading">';
		html += '<h6 class="mb-1"><i class="bi bi-stars"></i> Your Fit for ' + escapeHtml(report.job_title || 'this job') + '</h6>';
		if (report.from_cache) {
			html += '<div class="text-muted small mt-1"><i class="bi bi-clock-history"></i> Cached result (your CV and this job haven\u2019t changed since your last check)</div>';
		}
		html += '</div>';
		html += '</div>';

		html += '<div class="rt-ai-section">';
		html += '<h6 class="rt-ai-section-title"><i class="bi bi-file-text"></i> Summary</h6>';
		html += '<p class="mb-0">' + escapeHtml(report.summary || '') + '</p>';
		html += '</div>';

		html += '<div class="row g-3">';
		html += '<div class="col-md-6"><div class="rt-ai-section rt-ai-section-good h-100">'
			+ '<h6 class="rt-ai-section-title"><i class="bi bi-check-circle"></i> What matches</h6>' + renderList(report.matching_skills) + '</div></div>';
		html += '<div class="col-md-6"><div class="rt-ai-section rt-ai-section-bad h-100">'
			+ '<h6 class="rt-ai-section-title"><i class="bi bi-exclamation-circle"></i> What\u2019s missing</h6>' + renderList(report.missing_skills) + '</div></div>';
		html += '</div>';

		html += '<div class="rt-ai-section mt-3">';
		html += '<h6 class="rt-ai-section-title"><i class="bi bi-lightbulb"></i> Improve your CV before applying</h6>' + renderList(report.cv_improvement_tips);
		html += '</div>';
		html += '</div></div>';

		return html;
	}

	document.addEventListener('click', function (event) {
		var fitButton = event.target.closest('.rt-ai-fit-btn');
		if (!fitButton || typeof recruittechAjax === 'undefined') {
			return;
		}

		var jobId = fitButton.getAttribute('data-job-id');
		var resultEl = document.getElementById('rtAiFitResult');
		if (!resultEl) {
			return;
		}

		fitButton.disabled = true;
		resultEl.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted mt-2">'
			+ '<div class="spinner-border spinner-border-sm" role="status"></div>'
			+ '<span>Comparing your CV to this job&hellip;</span></div>';

		var formData = new FormData();
		formData.append('action', 'recruittech_check_job_fit');
		formData.append('nonce', recruittechAjax.analyzeNonce);
		formData.append('job_id', jobId);

		fetch(recruittechAjax.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (payload && payload.success) {
					resultEl.innerHTML = renderFitReport(payload.data);
				} else {
					var message = (payload && payload.data && payload.data.message) ? payload.data.message : 'Something went wrong while checking your fit for this job.';
					resultEl.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>';
				}
			})
			.catch(function () {
				resultEl.innerHTML = '<div class="alert alert-danger mb-0">Could not reach the AI Assistant. Please try again.</div>';
			})
			.finally(function () {
				fitButton.disabled = false;
			});
	});
})();
