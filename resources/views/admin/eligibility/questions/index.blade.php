@extends('layouts.admin')

@section('title', 'eDonate - Question Management')
@section('admin_page_class', 'admin-questions-page')
@section('layout_wrapper_class', 'app')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_link_mode', 'link')
@section('render_default_hamburger', 'false')

@section('header_title', 'Question Management')
@section('header_subtitle', 'Create and manage eligibility screening questions')

@section('header_slot')
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <button type="button" class="btn btn-primary" id="addQuestionBtn" aria-label="Add new question">
        <span aria-hidden="true">+</span> Add Question
    </button>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'question-management',
    'questionsPayload' => $questionsPayload ?? [
        'api' => [
            'listUrl'   => '',
            'storeUrl'  => '',
            'updateUrl' => '',
            'toggleUrl' => '',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<main class="main questions-main container-fluid px-0">
    <section class="content questions-content container-fluid py-3" aria-label="Questions content">
        <!-- Stats Cards -->
        <div class="questions-stats row g-3" aria-label="Questions summary">
            <div class="col-12 col-md-4">
                <article class="stat-card questions-stat questions-stat--total h-100">
                    <p class="questions-stat__label">Total Questions</p>
                    <p class="questions-stat__value" id="questionsStatTotal">0</p>
                </article>
            </div>
            <div class="col-6 col-md-4">
                <article class="stat-card questions-stat questions-stat--active h-100">
                    <p class="questions-stat__label">Active</p>
                    <p class="questions-stat__value" id="questionsStatActive">0</p>
                </article>
            </div>
            <div class="col-6 col-md-4">
                <article class="stat-card questions-stat questions-stat--inactive h-100">
                    <p class="questions-stat__label">Inactive</p>
                    <p class="questions-stat__value" id="questionsStatInactive">0</p>
                </article>
            </div>
        </div>

        <!-- Filters -->
        <form class="questions-filter row g-3 align-items-center mt-3" role="search" aria-label="Filter questions" action="#" method="get" onsubmit="return false;">
            <div class="questions-filter__search col-12 col-lg">
                <span class="questions-filter__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="16.5" y1="16.5" x2="22" y2="22"></line>
                    </svg>
                </span>
                <input id="questionsSearchInput" type="search" class="questions-filter__input form-control" placeholder="Search questions..." aria-label="Search questions">
            </div>

            <div class="questions-filter__select-wrap col-12 col-md-6 col-xl-3">
                <span class="questions-filter__select-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707l-4.414 4.414a1 1 0 00-.293.707v5.172a1 1 0 01-.414.828l-2 1.5a1 1 0 01-1.586-.828v-6.172a1 1 0 00-.293-.707L3.293 6.707A1 1 0 013 6V4z" stroke="currentColor" stroke-width="1.5"></path>
                    </svg>
                </span>
                <select id="questionsStatusFilter" class="questions-filter__select form-select" aria-label="Filter by status" name="is_active">
                    <option value="">All Questions</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <button type="button" class="btn btn-primary col-12 col-md-auto" id="questionsRefreshBtn" aria-label="Refresh questions">
                <span aria-hidden="true">↻</span> Refresh
            </button>
        </form>

        <!-- Table -->
        <div class="questions-table-wrapper mt-4 table-responsive">
            <table class="questions-table table table-hover" role="grid" aria-label="Questions table">
                <thead>
                    <tr>
                        <th scope="col" style="width: 80px;">Order</th>
                        <th scope="col">Question</th>
                        <th scope="col">Follow-up Trigger</th>
                        <th scope="col">Follow-up Prompt</th>
                        <th scope="col" style="width: 100px;">Status</th>
                        <th scope="col" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Loading questions...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav class="questions-pagination mt-4" aria-label="Table pagination">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span id="questionsPaginationInfo" class="text-muted">Loading...</span>
                </div>
                <div class="col-auto ms-auto">
                    <div class="btn-group" role="group" aria-label="Pagination controls">
                        <button type="button" class="btn btn-outline-secondary" id="questionsPrevBtn" aria-label="Previous page">Previous</button>
                        <button type="button" class="btn btn-outline-secondary" id="questionsNextBtn" aria-label="Next page">Next</button>
                    </div>
                </div>
            </div>
        </nav>
    </section>
</main>

<!-- Add/Edit Question Modal -->
<div class="modal fade" id="questionFormModal" tabindex="-1" aria-labelledby="questionFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionFormLabel">Add Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="questionForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="questionIdField" value="">

                    <div class="mb-3">
                        <label for="questionTextField" class="form-label">Question Text *</label>
                        <textarea id="questionTextField" class="form-control" rows="3" placeholder="Enter the main question..." required></textarea>
                        <div class="invalid-feedback" id="questionTextError">Please provide a valid question text.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="questionOrderField" class="form-label">Question Order *</label>
                            <input type="number" id="questionOrderField" class="form-control" min="1" max="999" value="1" required>
                            <div class="invalid-feedback" id="questionOrderError">Please enter a valid order number.</div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="followupTriggerField" class="form-label">Follow-up Trigger Answer</label>
                            <select id="followupTriggerField" class="form-select">
                                <option value="">None (No follow-up needed)</option>
                                <option value="yes">When answer is 'Yes'</option>
                                <option value="no">When answer is 'No'</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="followupPromptWrapper">
                        <label for="followupPromptField" class="form-label">Follow-up Prompt</label>
                        <textarea id="followupPromptField" class="form-control" rows="2" placeholder="e.g. Could you please describe why..."></textarea>
                        <div class="form-text">This prompt will be shown to the donor if they select the trigger answer.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="questionSubmitBtn">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" role="region" aria-live="polite" aria-atomic="true"></div>

@endsection

@push('admin_scripts')
<script src="{{ asset('js/admin/questions-management.js') }}"></script>
@endpush
