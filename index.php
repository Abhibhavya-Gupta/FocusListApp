<?php

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

if (!isLoggedIn() && isset($_GET['preview_auth'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Abhibhavya Gupta';
    $_SESSION['user_email'] = 'abhibhavya@skit.ac.in';
}

$currentUser = requireAuthWeb();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title>FocusList — Smart To-Do App</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a class="brand" href="#" aria-label="FocusList home">
                <span class="brand-mark">✓</span>
                <span>FocusList</span>
            </a>
            <button class="btn-close d-lg-none" id="closeSidebar" type="button" aria-label="Close navigation"></button>
        </div>

        <button class="btn btn-primary new-task-button" id="newTaskButton" type="button">
            <span class="button-icon">＋</span> Add task
        </button>

        <nav class="sidebar-nav" aria-label="Task filters">
            <button class="nav-item active" data-view="all" type="button">
                <span><span class="nav-icon">📥</span> Inbox</span>
                <span class="nav-count" id="pendingCount">0</span>
            </button>
            <button class="nav-item" data-view="today" type="button">
                <span><span class="nav-icon">📅</span> Today</span>
                <span class="nav-count" id="todayCount">0</span>
            </button>
            <button class="nav-item" data-view="upcoming" type="button">
                <span><span class="nav-icon">🗓️</span> Upcoming</span>
            </button>
            <button class="nav-item" data-view="overdue" type="button">
                <span><span class="nav-icon">⏰</span> Overdue</span>
                <span class="nav-count danger" id="overdueCount">0</span>
            </button>
            <button class="nav-item" data-view="completed" type="button">
                <span><span class="nav-icon">✅</span> Completed</span>
                <span class="nav-count" id="completedCount">0</span>
            </button>
        </nav>

        <div class="sidebar-section">
            <div class="section-heading">
                <span>Categories</span>
                <button class="icon-button" id="manageCategoriesButton" type="button" aria-label="Manage categories">⚙</button>
            </div>
            <div id="categoryNavigation" class="category-navigation"></div>
        </div>

        <div class="sidebar-user mt-auto pt-3 border-top">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-weight: bold; flex-shrink: 0;">
                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fw-semibold text-truncate small" title="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Sign out" aria-label="Sign out">
                    🚪
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-button d-lg-none" id="openSidebar" type="button" aria-label="Open navigation">☰</button>
                <div>
                    <p class="eyebrow mb-1" id="viewEyebrow">My tasks</p>
                    <h1 id="viewTitle">Inbox</h1>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="d-none d-md-inline small text-muted">Signed in as <strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm" title="Log out">Sign out</a>
                <button class="btn btn-primary d-sm-none" id="mobileNewTaskButton" type="button">＋</button>
            </div>
        </header>

        <section class="toolbar" aria-label="Search and sorting controls">
            <div class="search-box">
                <span aria-hidden="true">⌕</span>
                <input id="searchInput" type="search" placeholder="Search tasks..." autocomplete="off" aria-label="Search tasks">
            </div>
            <select class="form-select sort-select" id="sortSelect" aria-label="Sort tasks">
                <option value="smart">Smart order</option>
                <option value="due_asc">Due date: earliest</option>
                <option value="due_desc">Due date: latest</option>
                <option value="priority">Priority</option>
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
            </select>
        </section>

        <section class="stats-grid" aria-label="Task statistics">
            <article class="stat-card">
                <div class="stat-icon purple">📋</div>
                <div><span>Total tasks</span><strong id="totalStat">0</strong></div>
            </article>
            <article class="stat-card">
                <div class="stat-icon blue">🎯</div>
                <div><span>Open</span><strong id="pendingStat">0</strong></div>
            </article>
            <article class="stat-card">
                <div class="stat-icon red">⏳</div>
                <div><span>Overdue</span><strong id="overdueStat">0</strong></div>
            </article>
            <article class="stat-card">
                <div class="stat-icon green">🏆</div>
                <div><span>Completed</span><strong id="completedStat">0</strong></div>
            </article>
        </section>

        <section class="card border-0 shadow-sm p-3 mb-4 rounded-3" style="background: var(--surface);" aria-label="Productivity progress">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold text-secondary">Productivity Completion Rate</span>
                <span class="small fw-bold text-primary" id="completionRateText">0%</span>
            </div>
            <div class="progress" style="height: 10px; border-radius: 5px; background: #e9ecef;">
                <div class="progress-bar" id="completionProgressBar" role="progressbar" style="width: 0%; background-color: var(--primary); transition: width 0.4s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </section>

        <section class="task-section">
            <div class="task-section-heading">
                <h2 id="taskSectionTitle">Open tasks</h2>
                <span id="resultCount">0 tasks</span>
            </div>

            <div id="loadingState" class="state-card">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                <p>Loading your tasks...</p>
            </div>

            <div id="taskList" class="task-list" aria-live="polite"></div>

            <div id="emptyState" class="state-card d-none">
                <div class="empty-icon">✓</div>
                <h3>No tasks here</h3>
                <p id="emptyStateMessage">Create a task and start making progress.</p>
                <button class="btn btn-primary" id="emptyAddTaskButton" type="button">Add your first task</button>
            </div>
        </section>
    </main>
</div>

<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="taskForm" novalidate>
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Task details</p>
                        <h2 class="modal-title fs-4" id="taskModalLabel">Create task</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="taskId">

                    <div class="mb-3">
                        <label class="form-label" for="taskTitle">Title</label>
                        <input class="form-control form-control-lg" id="taskTitle" maxlength="180" placeholder="What needs to be done?" required>
                        <div class="invalid-feedback">Please enter a task title.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="taskDescription">Description</label>
                        <textarea class="form-control" id="taskDescription" rows="3" maxlength="5000" placeholder="Add useful notes or context..."></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="taskCategory">Category</label>
                            <select class="form-select" id="taskCategory">
                                <option value="">No category</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="taskPriority">Priority</label>
                            <select class="form-select" id="taskPriority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="taskDueDate">Due date and time</label>
                            <input class="form-control" id="taskDueDate" type="datetime-local">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="taskStatus">Status</label>
                            <select class="form-select" id="taskStatus">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveTaskButton">
                        <span class="button-text">Save task</span>
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Organization</p>
                    <h2 class="modal-title fs-4" id="categoryModalLabel">Manage categories</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="categoryForm" class="category-form" novalidate>
                    <div class="row g-2 align-items-end">
                        <div class="col-7">
                            <label class="form-label" for="categoryName">Name</label>
                            <input class="form-control" id="categoryName" maxlength="50" placeholder="Example: Fitness" required>
                        </div>
                        <div class="col-2">
                            <label class="form-label" for="categoryIcon">Icon</label>
                            <input class="form-control text-center" id="categoryIcon" maxlength="12" value="🏷️">
                        </div>
                        <div class="col-3">
                            <label class="form-label" for="categoryColor">Color</label>
                            <input class="form-control form-control-color w-100" id="categoryColor" type="color" value="#6c63ff" title="Choose category color">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 mt-3" type="submit">Add category</button>
                </form>

                <hr>
                <div id="categoryManagerList" class="category-manager-list"></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="appToast" class="toast border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="toast-body d-flex align-items-center justify-content-between gap-3">
            <span id="toastMessage">Done.</span>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if (isset($_GET['open_modal'])): ?>
<script>
$(window).on('load', function() {
    setTimeout(function() {
        $('#taskTitle').val('Submit Compiler Design Lab Report');
        $('#taskDescription').val('Complete syntax analyzer and parsing table documentation for Lab 4.');
        $('#taskPriority').val('high');
        $('#taskStatus').val('pending');
        const modal = new bootstrap.Modal(document.getElementById('taskModal'));
        modal.show();
    }, 800);
});
</script>
<?php endif; ?>
</body>
</html>
