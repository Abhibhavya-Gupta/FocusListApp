'use strict';

$(function () {
    const state = {
        view: 'all',
        categoryId: null,
        search: '',
        sort: 'smart',
        tasks: [],
        categories: [],
        summary: {},
    };

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    const toast = new bootstrap.Toast(document.getElementById('appToast'), { delay: 2600 });

    let searchTimer = null;

    $.ajaxSetup({
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        headers: {
            'X-CSRF-Token': csrfToken,
        },
        error: function (xhr) {
            if (xhr.status === 401) {
                window.location.href = 'login.php';
            }
        }
    });

    initialize();

    function initialize() {
        bindEvents();
        loadCategories();
        loadTasks();
    }

    function bindEvents() {
        $('#newTaskButton, #mobileNewTaskButton, #emptyAddTaskButton').on('click', openCreateTaskModal);

        $('#manageCategoriesButton').on('click', function () {
            renderCategoryManager();
            categoryModal.show();
        });

        $('.sidebar-nav').on('click', '.nav-item', function () {
            state.view = String($(this).data('view'));
            state.categoryId = null;
            $('.nav-item, .category-nav-item').removeClass('active');
            $(this).addClass('active');
            updateViewHeading();
            closeSidebar();
            loadTasks();
        });

        $('#categoryNavigation').on('click', '.category-nav-item', function () {
            state.categoryId = Number($(this).data('category-id'));
            state.view = 'all';
            $('.nav-item, .category-nav-item').removeClass('active');
            $(this).addClass('active');
            updateViewHeading();
            closeSidebar();
            loadTasks();
        });

        $('#searchInput').on('input', function () {
            clearTimeout(searchTimer);
            state.search = $(this).val().trim();
            searchTimer = setTimeout(loadTasks, 280);
        });

        $('#sortSelect').on('change', function () {
            state.sort = $(this).val();
            loadTasks();
        });

        $('#taskForm').on('submit', saveTask);
        $('#categoryForm').on('submit', saveCategory);

        $('#taskList').on('change', '.task-checkbox', toggleTaskStatus);
        $('#taskList').on('click', '.edit-task', openEditTaskModal);
        $('#taskList').on('click', '.delete-task', deleteTask);
        $('#categoryManagerList').on('click', '.delete-category', deleteCategory);

        $('#openSidebar').on('click', openSidebar);
        $('#closeSidebar, #sidebarOverlay').on('click', closeSidebar);
    }

    function apiRequest(url, method = 'GET', data = null) {
        const options = {
            url,
            method,
        };

        if (data !== null) {
            options.data = JSON.stringify(data);
        }

        return $.ajax(options);
    }

    function loadTasks() {
        showLoading(true);

        const query = $.param({
            view: state.view,
            category_id: state.categoryId || '',
            search: state.search,
            sort: state.sort,
        });

        apiRequest(`api/tasks.php?${query}`)
            .done(function (response) {
                state.tasks = response.tasks || [];
                state.summary = response.summary || {};
                renderTasks();
                renderSummary();
            })
            .fail(handleAjaxError)
            .always(function () {
                showLoading(false);
            });
    }

    function loadCategories() {
        apiRequest('api/categories.php')
            .done(function (response) {
                state.categories = response.categories || [];
                renderCategoryNavigation();
                renderCategoryOptions();
                renderCategoryManager();
            })
            .fail(handleAjaxError);
    }

    function renderTasks() {
        const $taskList = $('#taskList').empty();
        const hasTasks = state.tasks.length > 0;

        $('#emptyState').toggleClass('d-none', hasTasks);
        $('#resultCount').text(`${state.tasks.length} ${state.tasks.length === 1 ? 'task' : 'tasks'}`);

        if (!hasTasks) {
            $('#emptyStateMessage').text(emptyStateMessage());
            return;
        }

        state.tasks.forEach(function (task) {
            $taskList.append(createTaskCard(task));
        });
    }

    function createTaskCard(task) {
        const priorityColor = {
            low: '#4c9bd6',
            medium: '#6c63ff',
            high: '#f59f00',
            urgent: '#e5484d',
        }[task.priority] || '#6c63ff';

        const dueClass = task.is_overdue ? 'overdue' : (task.is_today ? 'today' : '');
        const dueLabel = task.due_date ? formatDueDate(task.due_date, task.is_overdue, task.is_today) : '';
        const categoryColor = isValidHexColor(task.category_color) ? task.category_color : '#747887';
        const checked = task.status === 'completed' ? 'checked' : '';
        const completedClass = task.status === 'completed' ? 'completed' : '';

        const descriptionHtml = task.description
            ? `<p class="task-description">${escapeHtml(task.description)}</p>`
            : '';

        const categoryHtml = task.category_name
            ? `<span class="meta-chip"><span class="category-dot" style="background:${categoryColor};margin-right:1px"></span>${escapeHtml(task.category_icon || '🏷️')} ${escapeHtml(task.category_name)}</span>`
            : '';

        const dueHtml = task.due_date
            ? `<span class="meta-chip ${dueClass}">🕒 ${escapeHtml(dueLabel)}</span>`
            : '';

        return `
            <article class="task-card ${completedClass}" style="--priority-color:${priorityColor}" data-task-id="${task.id}">
                <input class="form-check-input task-checkbox" type="checkbox" ${checked} aria-label="Mark ${escapeHtml(task.title)} as complete">
                <div class="task-main">
                    <div class="task-title-row">
                        <h3 class="task-title">${escapeHtml(task.title)}</h3>
                        <span class="priority-chip ${escapeHtml(task.priority)}">${capitalize(task.priority)}</span>
                    </div>
                    ${descriptionHtml}
                    <div class="task-meta">
                        ${categoryHtml}
                        ${dueHtml}
                    </div>
                </div>
                <div class="task-actions">
                    <button class="task-action-button edit-task" type="button" aria-label="Edit task" title="Edit">✎</button>
                    <button class="task-action-button delete delete-task" type="button" aria-label="Delete task" title="Delete">🗑</button>
                </div>
            </article>
        `;
    }

    function renderSummary() {
        $('#totalStat').text(state.summary.total || 0);
        $('#pendingStat').text(state.summary.pending || 0);
        $('#overdueStat').text(state.summary.overdue || 0);
        $('#completedStat').text(state.summary.completed || 0);

        $('#pendingCount').text(state.summary.pending || 0);
        $('#todayCount').text(state.summary.today || 0);
        $('#overdueCount').text(state.summary.overdue || 0);
        $('#completedCount').text(state.summary.completed || 0);

        const rate = Number(state.summary.completion_rate || 0);
        $('#completionRateText').text(rate + '%');
        $('#completionProgressBar').css('width', rate + '%').attr('aria-valuenow', rate);
    }

    function renderCategoryNavigation() {
        const $container = $('#categoryNavigation').empty();

        state.categories.forEach(function (category) {
            const color = isValidHexColor(category.color) ? category.color : '#747887';
            const active = state.categoryId === category.id ? 'active' : '';

            $container.append(`
                <button class="category-nav-item ${active}" type="button" data-category-id="${category.id}">
                    <span><span class="category-dot" style="background:${color}"></span>${escapeHtml(category.icon || '🏷️')} ${escapeHtml(category.name)}</span>
                    <span class="nav-count">${category.pending_count}</span>
                </button>
            `);
        });
    }

    function renderCategoryOptions() {
        const currentValue = $('#taskCategory').val();
        const $select = $('#taskCategory').empty().append('<option value="">No category</option>');

        state.categories.forEach(function (category) {
            $select.append(
                $('<option>', {
                    value: category.id,
                    text: `${category.icon || '🏷️'} ${category.name}`,
                })
            );
        });

        $select.val(currentValue || '');
    }

    function renderCategoryManager() {
        const $container = $('#categoryManagerList').empty();

        if (state.categories.length === 0) {
            $container.html('<p class="text-secondary text-center mb-0">No categories found.</p>');
            return;
        }

        state.categories.forEach(function (category) {
            const color = isValidHexColor(category.color) ? category.color : '#747887';
            const deleteButton = category.is_default
                ? '<span class="badge text-bg-light">Default</span>'
                : `<button class="btn btn-sm btn-outline-danger delete-category" type="button" data-category-id="${category.id}">Delete</button>`;

            $container.append(`
                <div class="category-manager-item">
                    <div class="category-manager-name">
                        <span class="category-dot" style="background:${color};margin-right:0"></span>
                        <span>${escapeHtml(category.icon || '🏷️')}</span>
                        <div>
                            <strong>${escapeHtml(category.name)}</strong>
                            <small class="d-block">${category.task_count} ${category.task_count === 1 ? 'task' : 'tasks'}</small>
                        </div>
                    </div>
                    ${deleteButton}
                </div>
            `);
        });
    }

    function openCreateTaskModal() {
        resetTaskForm();
        $('#taskModalLabel').text('Create task');
        $('#saveTaskButton .button-text').text('Create task');
        taskModal.show();
        setTimeout(function () { $('#taskTitle').trigger('focus'); }, 180);
    }

    function openEditTaskModal() {
        const id = Number($(this).closest('.task-card').data('task-id'));
        const task = state.tasks.find(function (item) { return item.id === id; });

        if (!task) {
            showToast('Task could not be found in the current list.', true);
            return;
        }

        resetTaskForm();
        $('#taskId').val(task.id);
        $('#taskTitle').val(task.title);
        $('#taskDescription').val(task.description || '');
        $('#taskCategory').val(task.category_id || '');
        $('#taskPriority').val(task.priority);
        $('#taskStatus').val(task.status);
        $('#taskDueDate').val(toDateTimeLocal(task.due_date));
        $('#taskModalLabel').text('Edit task');
        $('#saveTaskButton .button-text').text('Save changes');
        taskModal.show();
    }

    function resetTaskForm() {
        const form = document.getElementById('taskForm');
        form.reset();
        form.classList.remove('was-validated');
        $('#taskId').val('');
        $('#taskCategory').val('');
        $('#taskPriority').val('medium');
        $('#taskStatus').val('pending');
    }

    function saveTask(event) {
        event.preventDefault();

        const form = event.currentTarget;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const id = Number($('#taskId').val()) || null;
        const payload = {
            id,
            title: $('#taskTitle').val().trim(),
            description: $('#taskDescription').val().trim(),
            category_id: $('#taskCategory').val() || null,
            priority: $('#taskPriority').val(),
            due_date: $('#taskDueDate').val() || null,
            status: $('#taskStatus').val(),
        };

        setButtonLoading('#saveTaskButton', true);

        apiRequest('api/tasks.php', id ? 'PUT' : 'POST', payload)
            .done(function (response) {
                taskModal.hide();
                showToast(response.message || 'Task saved.');
                loadTasks();
                loadCategories();
            })
            .fail(handleAjaxError)
            .always(function () {
                setButtonLoading('#saveTaskButton', false);
            });
    }

    function toggleTaskStatus() {
        const $checkbox = $(this);
        const id = Number($checkbox.closest('.task-card').data('task-id'));
        const status = $checkbox.is(':checked') ? 'completed' : 'pending';

        $checkbox.prop('disabled', true);

        apiRequest('api/tasks.php', 'PATCH', { id, status })
            .done(function (response) {
                showToast(response.message || 'Task updated.');
                loadTasks();
                loadCategories();
            })
            .fail(function (xhr) {
                $checkbox.prop('checked', !$checkbox.is(':checked'));
                handleAjaxError(xhr);
            })
            .always(function () {
                $checkbox.prop('disabled', false);
            });
    }

    function deleteTask() {
        const id = Number($(this).closest('.task-card').data('task-id'));
        const task = state.tasks.find(function (item) { return item.id === id; });

        if (!window.confirm(`Delete “${task ? task.title : 'this task'}”?`)) {
            return;
        }

        apiRequest('api/tasks.php', 'DELETE', { id })
            .done(function (response) {
                showToast(response.message || 'Task deleted.');
                loadTasks();
                loadCategories();
            })
            .fail(handleAjaxError);
    }

    function saveCategory(event) {
        event.preventDefault();

        const form = event.currentTarget;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            name: $('#categoryName').val().trim(),
            icon: $('#categoryIcon').val().trim() || '🏷️',
            color: $('#categoryColor').val(),
        };

        apiRequest('api/categories.php', 'POST', payload)
            .done(function (response) {
                showToast(response.message || 'Category created.');
                form.reset();
                form.classList.remove('was-validated');
                $('#categoryIcon').val('🏷️');
                $('#categoryColor').val('#6c63ff');
                loadCategories();
            })
            .fail(handleAjaxError);
    }

    function deleteCategory() {
        const id = Number($(this).data('category-id'));

        if (!window.confirm('Delete this category?')) {
            return;
        }

        apiRequest('api/categories.php', 'DELETE', { id })
            .done(function (response) {
                if (state.categoryId === id) {
                    state.categoryId = null;
                    state.view = 'all';
                    $('.nav-item, .category-nav-item').removeClass('active');
                    $('.nav-item[data-view="all"]').addClass('active');
                    updateViewHeading();
                    loadTasks();
                }
                showToast(response.message || 'Category deleted.');
                loadCategories();
            })
            .fail(handleAjaxError);
    }

    function updateViewHeading() {
        const viewLabels = {
            all: ['My tasks', 'Inbox', 'Open tasks'],
            today: ['Focus for today', 'Today', 'Due today'],
            upcoming: ['Plan ahead', 'Upcoming', 'Upcoming tasks'],
            overdue: ['Needs attention', 'Overdue', 'Overdue tasks'],
            completed: ['Your progress', 'Completed', 'Completed tasks'],
        };

        let labels = viewLabels[state.view] || viewLabels.all;

        if (state.categoryId !== null) {
            const category = state.categories.find(function (item) { return item.id === state.categoryId; });
            if (category) {
                labels = ['Category', `${category.icon || '🏷️'} ${category.name}`, `${category.name} tasks`];
            }
        }

        $('#viewEyebrow').text(labels[0]);
        $('#viewTitle').text(labels[1]);
        $('#taskSectionTitle').text(labels[2]);
    }

    function emptyStateMessage() {
        if (state.search) {
            return `No task matches “${state.search}”. Try another search.`;
        }

        const messages = {
            all: 'Your inbox is clear. Add a task when something comes up.',
            today: 'Nothing is due today. Enjoy the breathing room.',
            upcoming: 'No upcoming deadlines are scheduled.',
            overdue: 'Great work—nothing is overdue.',
            completed: 'Completed tasks will appear here.',
        };

        if (state.categoryId !== null) {
            return 'This category does not have any open tasks yet.';
        }

        return messages[state.view] || messages.all;
    }

    function showLoading(isLoading) {
        $('#loadingState').toggleClass('d-none', !isLoading);
        if (isLoading) {
            $('#taskList').empty();
            $('#emptyState').addClass('d-none');
        }
    }

    function setButtonLoading(selector, loading) {
        const $button = $(selector);
        $button.prop('disabled', loading);
        $button.find('.button-text').toggleClass('d-none', loading);
        $button.find('.spinner-border').toggleClass('d-none', !loading);
    }

    function showToast(message, isError = false) {
        $('#toastMessage').text(message);
        $('#appToast').toggleClass('error-toast', isError);
        toast.show();
    }

    function handleAjaxError(xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Something went wrong. Check your database and server configuration.';

        showToast(message, true);
        console.error('API request failed:', xhr.status, message);
    }

    function formatDueDate(value, isOverdue, isToday) {
        const date = parseSqlDate(value);
        if (!date) {
            return 'Invalid date';
        }

        const time = new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);

        if (isToday) {
            return `Today, ${time}`;
        }

        const formattedDate = new Intl.DateTimeFormat(undefined, {
            day: 'numeric',
            month: 'short',
            year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined,
        }).format(date);

        return isOverdue ? `Overdue · ${formattedDate}, ${time}` : `${formattedDate}, ${time}`;
    }

    function parseSqlDate(value) {
        if (!value) {
            return null;
        }

        const parsed = new Date(value.replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function toDateTimeLocal(value) {
        if (!value) {
            return '';
        }
        return value.replace(' ', 'T').slice(0, 16);
    }

    function capitalize(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    function isValidHexColor(value) {
        return /^#[0-9a-f]{6}$/i.test(value || '');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function openSidebar() {
        $('#sidebar').addClass('open');
        $('#sidebarOverlay').addClass('show');
    }

    function closeSidebar() {
        $('#sidebar').removeClass('open');
        $('#sidebarOverlay').removeClass('show');
    }
});
