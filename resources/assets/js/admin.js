import '../scss/admin.scss';

class AdminMenuManager {
    constructor() {
        this.form = document.getElementById('simplify-admin-menus-form');
        if (!this.form) {
            return;
        }

        this.roleInputs = this.form.querySelectorAll('input[name="selected_role"]');
        this.userInputs = this.form.querySelectorAll('input[name="selected_user"]');
        this.currentRoleSpan = document.querySelector('.simpad-current-role');
        this.currentRole = this.getCheckedRole();
        this.currentRoleName = this.getCheckedRoleName();
        this.currentUser = this.getCheckedUser();
        this.currentUserName = this.getCheckedUserName();
        this.currentTab = this.form.dataset.currentTab;
        this.userSearchInput = document.getElementById('simpad-user-search');
        this.loadingOverlay = document.querySelector('.simpad-loading-overlay');
        this.userStatus = document.getElementById('simpad-user-status');
        this.statusLabel = document.getElementById('simpad-status-label');
        this.resetButton = document.getElementById('simpad-reset-overrides');
        this.protectedNotice = document.getElementById('simpad-protected-notice');
        this.description = document.getElementById('simpad-settings-description');
        this.roleSettings = {};
        this.isProtected = false;
        this.mode = this.currentUser ? 'user' : 'role';

        this.init();
    }

    init() {
        const checkedRole = Array.from(this.roleInputs).find(input => input.checked);
        const checkedUser = Array.from(this.userInputs).find(input => input.checked);

        if (checkedRole) {
            checkedRole.closest('li').classList.add('active');
        }
        if (checkedUser) {
            checkedUser.closest('li').classList.add('active');
        }

        this.initializeRoleListeners();
        this.initializeUserListeners();
        this.initializeTabListeners();
        this.initializeUserSearch();
        this.initializeStateButtons();
        this.initializeResetButton();
        this.setMode(this.mode);

        if (this.currentUser) {
            this.loadSettings(null, this.currentUser);
            this.updateCurrentRoleIndicator(this.currentUserName);
        } else {
            this.loadSettings(this.currentRole);
            this.updateCurrentRoleIndicator(this.currentRoleName);
        }
    }

    getCheckedRole() {
        const checkedInput = Array.from(this.roleInputs).find(input => input.checked);
        return checkedInput ? checkedInput.value : null;
    }

    getCheckedRoleName() {
        const checkedInput = Array.from(this.roleInputs).find(input => input.checked);
        return checkedInput ? checkedInput.nextElementSibling.textContent.trim() : '';
    }

    getCheckedUser() {
        const checkedInput = Array.from(this.userInputs).find(input => input.checked);
        return checkedInput ? checkedInput.value : null;
    }

    getCheckedUserName() {
        const checkedInput = Array.from(this.userInputs).find(input => input.checked);
        return checkedInput ? checkedInput.nextElementSibling.textContent.split('(')[0].trim() : '';
    }

    getActiveContainer() {
        return this.currentTab === 'menu-items'
            ? document.querySelector('.simpad-menu-items-list')
            : document.querySelector('.simpad-admin-bar-items-list');
    }

    setMode(mode) {
        this.mode = mode;
        this.form.dataset.mode = mode;
        this.form.classList.toggle('is-user-mode', mode === 'user');
        this.form.classList.toggle('is-role-mode', mode === 'role');

        this.form.querySelectorAll('.simpad-role-control').forEach(el => {
            el.hidden = mode === 'user';
        });
        this.form.querySelectorAll('.simpad-user-control').forEach(el => {
            el.hidden = mode !== 'user';
        });

        this.form.querySelectorAll('.simpad-override-input').forEach(input => {
            input.disabled = mode !== 'user';
            if (mode === 'user' && input.value !== 'inherit') {
                input.name = `simpad_overrides[${input.dataset.itemId}]`;
            } else {
                input.removeAttribute('name');
            }
        });

        this.form.querySelectorAll('.simpad-role-control input[type="checkbox"]').forEach(input => {
            input.disabled = mode === 'user';
        });

        if (this.description) {
            this.description.textContent = mode === 'user'
                ? simplifyAdminMenus.strings.chooseUserOverrides
                : simplifyAdminMenus.strings.chooseHide;
        }

        // Status banner and reset only apply when editing a user.
        this.hideUserChrome();
    }

    hideUserChrome() {
        if (this.userStatus) {
            this.userStatus.hidden = true;
        }
        if (this.statusLabel) {
            this.statusLabel.textContent = '';
        }
        if (this.protectedNotice) {
            this.protectedNotice.hidden = true;
        }
    }

    handleParentChildCheckboxes(parentCheckbox) {
        const parentMenuItem = parentCheckbox.closest('.simpad-menu-item');
        const submenuContainer = parentMenuItem.querySelector('.simpad-submenu-items');
        if (!submenuContainer) return;

        const allSubmenuItems = submenuContainer.querySelectorAll('.simpad-role-control input[type="checkbox"]');
        if (allSubmenuItems.length === 0) return;

        const immediateChildren = this.currentTab === 'menu-items'
            ? submenuContainer.querySelectorAll(':scope > .simpad-menu-item > .simpad-role-control > input[type="checkbox"], :scope > .simpad-role-control > input[type="checkbox"]')
            : submenuContainer.querySelectorAll(':scope > .simpad-menu-item > .simpad-role-control > input[type="checkbox"]');

        parentCheckbox.addEventListener('change', () => {
            const isChecked = parentCheckbox.checked;
            allSubmenuItems.forEach(item => {
                item.checked = isChecked;
            });
            parentCheckbox.indeterminate = false;
        });

        allSubmenuItems.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateParentCheckboxState(parentCheckbox, immediateChildren);

                const grandparentCheckbox = parentMenuItem.closest('.simpad-submenu-items')
                    ?.closest('.simpad-menu-item')
                    ?.querySelector(':scope > .simpad-role-control > input[type="checkbox"]');

                if (grandparentCheckbox) {
                    const parentSiblings = grandparentCheckbox.closest('.simpad-menu-item')
                        .querySelector('.simpad-submenu-items')
                        .querySelectorAll(':scope > .simpad-menu-item > .simpad-role-control > input[type="checkbox"], :scope > .simpad-role-control > input[type="checkbox"]');
                    this.updateParentCheckboxState(grandparentCheckbox, parentSiblings);
                }
            });
        });

        this.updateParentCheckboxState(parentCheckbox, immediateChildren);
    }

    updateParentCheckboxState(parentCheckbox, children) {
        const childArray = Array.from(children);
        if (childArray.length === 0) {
            return;
        }

        const checkedCount = childArray.filter(child => child.checked).length;
        const totalCount = childArray.length;

        if (checkedCount === 0) {
            parentCheckbox.checked = false;
            parentCheckbox.indeterminate = false;
        } else if (checkedCount === totalCount) {
            parentCheckbox.checked = true;
            parentCheckbox.indeterminate = false;
        } else {
            parentCheckbox.checked = false;
            parentCheckbox.indeterminate = true;
        }
    }

    initializeCheckboxes() {
        const container = this.getActiveContainer();
        if (!container) {
            return;
        }

        container.querySelectorAll('.simpad-menu-item').forEach(item => {
            const checkbox = item.querySelector(':scope > .simpad-role-control > input[type="checkbox"]');
            if (checkbox) {
                this.handleParentChildCheckboxes(checkbox);
            }
        });
    }

    initializeStateButtons() {
        this.form.addEventListener('click', (event) => {
            const button = event.target.closest('.simpad-state-btn');
            if (!button || this.mode !== 'user') {
                return;
            }

            const item = button.closest('.simpad-menu-item');
            if (!item) {
                return;
            }

            const state = button.dataset.state;
            this.setItemState(item, state, true);
            this.updateStatusBanner();
        });
    }

    initializeResetButton() {
        if (!this.resetButton) {
            return;
        }

        this.resetButton.addEventListener('click', () => {
            if (this.mode !== 'user') {
                return;
            }

            const container = this.getActiveContainer();
            if (!container) {
                return;
            }

            container.querySelectorAll('.simpad-menu-item[data-item-id]').forEach(item => {
                this.setItemState(item, 'inherit', false);
            });

            this.updateStatusBanner();
        });
    }

    setItemState(item, state, propagate) {
        const input = item.querySelector(':scope > .simpad-user-control .simpad-override-input');
        if (!input) {
            return;
        }

        input.value = state;
        if (state === 'inherit') {
            input.removeAttribute('name');
        } else {
            input.name = `simpad_overrides[${input.dataset.itemId}]`;
        }

        item.querySelectorAll(':scope > .simpad-user-control .simpad-state-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.state === state);
        });

        this.updateRoleHint(item, state);

        if (propagate) {
            const submenu = item.querySelector(':scope > .simpad-submenu-items');
            if (submenu) {
                submenu.querySelectorAll('.simpad-menu-item[data-item-id]').forEach(child => {
                    this.setItemState(child, state, false);
                });
            }
        }
    }

    updateRoleHint(item, state) {
        const hint = item.querySelector(':scope > .simpad-user-control .simpad-role-hint');
        if (!hint) {
            return;
        }

        const itemId = item.dataset.itemId;
        const hiddenByRole = Boolean(this.roleSettings[itemId]);
        hint.hidden = !(state === 'inherit' && hiddenByRole);
    }

    updateStatusBanner() {
        if (!this.userStatus) {
            return;
        }

        if (this.mode !== 'user') {
            this.hideUserChrome();
            return;
        }

        if (this.isProtected) {
            this.userStatus.hidden = true;
            if (this.statusLabel) {
                this.statusLabel.textContent = '';
            }
            if (this.protectedNotice) {
                this.protectedNotice.hidden = false;
            }
            return;
        }

        if (this.protectedNotice) {
            this.protectedNotice.hidden = true;
        }

        const container = this.getActiveContainer();
        const overrides = container
            ? Array.from(container.querySelectorAll('.simpad-override-input')).filter(input => input.value !== 'inherit')
            : [];

        const strings = simplifyAdminMenus.strings || {};
        const labelText = overrides.length === 0
            ? (strings.usingRoleDefaults || '')
            : (strings.customOverrides || '').replace('%d', String(overrides.length));

        if (this.statusLabel) {
            this.statusLabel.textContent = labelText;
        }

        this.userStatus.hidden = !labelText;
    }

    updateCurrentRoleIndicator(name) {
        if (!this.currentRoleSpan) {
            return;
        }

        this.currentRoleSpan.style.opacity = '0';
        setTimeout(() => {
            this.currentRoleSpan.textContent = `${simplifyAdminMenus.strings.editing} ${name}`;
            this.currentRoleSpan.style.opacity = '1';
        }, 200);
    }

    showLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add('active');
            this.form.classList.add('is-loading');
        }
    }

    hideLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.remove('active');
            this.form.classList.remove('is-loading');
        }
    }

    async loadSettings(role = null, userId = null) {
        this.showLoading();

        try {
            const formData = new FormData();
            formData.append('action', 'load_settings');
            formData.append('nonce', simplifyAdminMenus.nonce);
            formData.append('tab', this.currentTab);

            if (role) {
                formData.append('role', role);
            }
            if (userId) {
                formData.append('user_id', userId);
            }

            const response = await fetch(simplifyAdminMenus.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!data.success) {
                return;
            }

            const payload = data.data || {};
            const container = this.getActiveContainer();
            if (!container) {
                return;
            }

            if (userId) {
                this.setMode('user');
                this.roleSettings = payload.role_settings || {};
                this.isProtected = Boolean(payload.is_protected);
                this.applyAccessFilter(container, payload);
                this.applyUserSettings(container, payload.settings || {});
                this.updateStatusBanner();
            } else {
                this.setMode('role');
                this.roleSettings = {};
                this.isProtected = false;
                this.applyAccessFilter(container, payload);
                this.applyRoleSettings(container, payload.settings || {});
                setTimeout(() => this.initializeCheckboxes(), 0);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Filter visible items for the selected role/user.
     * Menu items: use data-capability + actor capability list from the server.
     * Admin bar: use probed accessible node IDs from the server.
     * null/undefined means "do not filter" (show everything).
     */
    applyAccessFilter(container, payload) {
        if (this.currentTab === 'menu-items') {
            this.applyMenuCapabilityFilter(container, payload.capabilities);
            return;
        }

        this.applyAccessibleIdsFilter(container, payload.accessible_ids);
    }

    applyMenuCapabilityFilter(container, capabilities) {
        // Only filter when we received a real capability list.
        if (!Array.isArray(capabilities)) {
            this.showAllItems(container);
            return;
        }

        const caps = new Set(capabilities);

        container.querySelectorAll('.simpad-menu-item[data-item-id]').forEach(item => {
            const capability = item.dataset.capability || '';
            const allowed = capability === ''
                || capability === 'exist'
                || caps.has(capability);

            this.setItemAccess(item, allowed);
        });

        this.reenableVisibleControls(container);
    }

    applyAccessibleIdsFilter(container, accessibleIds) {
        // null = probing failed (e.g. role has no users) — keep full list.
        if (!Array.isArray(accessibleIds)) {
            this.showAllItems(container);
            return;
        }

        const allowed = new Set(accessibleIds);

        container.querySelectorAll('.simpad-menu-item[data-item-id]').forEach(item => {
            this.setItemAccess(item, allowed.has(item.dataset.itemId));
        });

        this.reenableVisibleControls(container);
    }

    showAllItems(container) {
        container.querySelectorAll('.simpad-menu-item[data-item-id]').forEach(item => {
            this.setItemAccess(item, true);
        });
        this.reenableVisibleControls(container);
    }

    setItemAccess(item, allowed) {
        item.hidden = !allowed;
        item.classList.toggle('simpad-inaccessible', !allowed);

        item.querySelectorAll(':scope > .simpad-role-control input, :scope > .simpad-user-control input').forEach(input => {
            if (!allowed) {
                input.disabled = true;
                if (input.classList.contains('simpad-override-input')) {
                    input.value = 'inherit';
                    input.removeAttribute('name');
                }
                if (input.type === 'checkbox') {
                    input.checked = false;
                    input.indeterminate = false;
                }
            }
        });
    }

    reenableVisibleControls(container) {
        if (this.mode === 'user') {
            container.querySelectorAll('.simpad-menu-item[data-item-id]:not([hidden]) > .simpad-user-control .simpad-override-input').forEach(input => {
                input.disabled = false;
                if (input.value !== 'inherit') {
                    input.name = `simpad_overrides[${input.dataset.itemId}]`;
                }
            });
            return;
        }

        container.querySelectorAll('.simpad-menu-item[data-item-id]:not([hidden]) > .simpad-role-control input[type="checkbox"]').forEach(input => {
            input.disabled = false;
        });
    }

    applyRoleSettings(container, settings) {
        container.querySelectorAll('.simpad-menu-item[data-item-id]:not([hidden]) .simpad-role-control input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
        });

        Object.keys(settings).forEach(key => {
            const item = container.querySelector(`.simpad-menu-item[data-item-id="${CSS.escape(key)}"]:not([hidden])`);
            const checkbox = item ? item.querySelector('.simpad-role-control input[type="checkbox"]') : null;
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }

    applyUserSettings(container, settings) {
        container.querySelectorAll('.simpad-menu-item[data-item-id]:not([hidden])').forEach(item => {
            const itemId = item.dataset.itemId;
            const state = settings[itemId] || 'inherit';
            this.setItemState(item, state, false);
        });
    }

    updateTabLinks(selectedRole = null, selectedUser = null) {
        const tabLinks = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
        const baseUrl = window.location.href.split('?')[0];
        const urlParams = new URLSearchParams();

        urlParams.set('page', 'simplify-admin-menus');
        urlParams.set('_wpnonce', simplifyAdminMenus.nonce);

        if (selectedUser) {
            urlParams.set('selected_user', selectedUser);
        } else if (selectedRole) {
            urlParams.set('selected_role', selectedRole);
        }

        tabLinks.forEach(link => {
            const isMenuItems = link.querySelector('.dashicons-menu-alt');
            urlParams.set('tab', isMenuItems ? 'menu-items' : 'admin-bar');
            link.href = `${baseUrl}?${urlParams.toString()}`;
        });
    }

    initializeRoleListeners() {
        this.roleInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (!input.checked) {
                    return;
                }

                this.currentRole = input.value;
                this.currentRoleName = input.nextElementSibling.textContent.trim();
                this.currentUser = null;
                this.currentUserName = '';

                this.userInputs.forEach(userInput => {
                    userInput.checked = false;
                    userInput.closest('li').classList.remove('active');
                });

                const url = new URL(window.location.href);
                url.searchParams.set('selected_role', this.currentRole);
                url.searchParams.delete('selected_user');
                url.searchParams.set('_wpnonce', simplifyAdminMenus.nonce);
                if (!url.searchParams.has('tab')) {
                    url.searchParams.set('tab', this.currentTab);
                }
                window.history.pushState({}, '', url);

                this.updateTabLinks(this.currentRole);
                this.updateCurrentRoleIndicator(this.currentRoleName);
                this.loadSettings(this.currentRole);

                document.querySelectorAll('.simpad-roles-list li').forEach(li => {
                    li.classList.remove('active');
                });
                input.closest('li').classList.add('active');
            });
        });
    }

    initializeUserListeners() {
        this.userInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (!input.checked) {
                    return;
                }

                this.currentUser = input.value;
                this.currentUserName = input.nextElementSibling.textContent.split('(')[0].trim();
                this.currentRole = null;
                this.currentRoleName = '';

                this.roleInputs.forEach(roleInput => {
                    roleInput.checked = false;
                    roleInput.closest('li').classList.remove('active');
                });

                const url = new URL(window.location.href);
                url.searchParams.set('selected_user', this.currentUser);
                url.searchParams.delete('selected_role');
                url.searchParams.set('_wpnonce', simplifyAdminMenus.nonce);
                if (!url.searchParams.has('tab')) {
                    url.searchParams.set('tab', this.currentTab);
                }
                window.history.pushState({}, '', url);

                this.updateTabLinks(null, this.currentUser);
                this.updateCurrentRoleIndicator(this.currentUserName);
                this.loadSettings(null, this.currentUser);

                document.querySelectorAll('.simpad-users-list li').forEach(li => {
                    li.classList.remove('active');
                });
                input.closest('li').classList.add('active');
            });
        });
    }

    initializeUserSearch() {
        if (!this.userSearchInput) {
            return;
        }

        this.userSearchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const userItems = document.querySelectorAll('.simpad-users-list li');

            userItems.forEach(item => {
                const userName = item.querySelector('span').textContent.toLowerCase();
                item.style.display = userName.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    initializeTabListeners() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const href = e.currentTarget.href;
                this.currentTab = new URL(href).searchParams.get('tab');
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new AdminMenuManager();
});
