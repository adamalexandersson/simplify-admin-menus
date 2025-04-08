import '../scss/admin.scss';

class AdminMenuManager {
    constructor() {
        this.form = document.getElementById('simplify-admin-form');
        this.roleInputs = this.form.querySelectorAll('input[name="selected_role"]');
        this.userInputs = this.form.querySelectorAll('input[name="selected_user"]');
        this.checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
        this.currentRoleSpan = document.querySelector('.sa-current-role');
        this.currentRole = this.getCheckedRole();
        this.currentRoleName = this.getCheckedRoleName();
        this.currentUser = this.getCheckedUser();
        this.currentUserName = this.getCheckedUserName();
        this.currentTab = this.form.dataset.currentTab;
        this.userSearchInput = document.getElementById('sa-user-search');
        this.loadingOverlay = document.querySelector('.sa-loading-overlay');

        this.init();
    }

    init() {
        // Set initial active state
        const checkedRole = Array.from(this.roleInputs).find(input => input.checked);
        const checkedUser = Array.from(this.userInputs).find(input => input.checked);
        
        if (checkedRole) {
            checkedRole.closest('li').classList.add('active');
        }
        if (checkedUser) {
            checkedUser.closest('li').classList.add('active');
        }

        // Initialize event listeners
        this.initializeRoleListeners();
        this.initializeUserListeners();
        this.initializeTabListeners();
        this.initializeCheckboxes();
        this.initializeUserSearch();

        // Load initial settings
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

    handleParentChildCheckboxes(parentCheckbox) {
        const parentMenuItem = parentCheckbox.closest('.sa-menu-item');
        const submenuContainer = parentMenuItem.querySelector('.sa-submenu-items');
        if (!submenuContainer) return;

        const allSubmenuItems = submenuContainer.querySelectorAll('input[type="checkbox"]');
        if (allSubmenuItems.length === 0) return;

        const immediateChildren = this.currentTab === 'menu-items'
            ? submenuContainer.querySelectorAll(':scope > label > input[type="checkbox"]')
            : submenuContainer.querySelectorAll(':scope > .sa-menu-item > label > input[type="checkbox"]');

        // Parent checkbox change handler
        parentCheckbox.addEventListener('change', () => {
            const isChecked = parentCheckbox.checked;
            allSubmenuItems.forEach(item => item.checked = isChecked);
            parentCheckbox.indeterminate = false;
        });

        // Children checkboxes change handler
        allSubmenuItems.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateParentCheckboxState(parentCheckbox, immediateChildren);
                
                // Update grandparent if exists
                const grandparentCheckbox = parentMenuItem.closest('.sa-submenu-items')
                    ?.closest('.sa-menu-item')
                    ?.querySelector('label > input[type="checkbox"]');
                    
                if (grandparentCheckbox) {
                    const parentSiblings = grandparentCheckbox.closest('.sa-menu-item')
                        .querySelector('.sa-submenu-items')
                        .querySelectorAll(':scope > label > input[type="checkbox"]');
                    this.updateParentCheckboxState(grandparentCheckbox, parentSiblings);
                }
            });
        });

        // Initialize state
        this.updateParentCheckboxState(parentCheckbox, immediateChildren);
    }

    updateParentCheckboxState(parentCheckbox, children) {
        const checkedCount = Array.from(children).filter(child => child.checked).length;
        const totalCount = children.length;

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
        const selector = this.currentTab === 'admin-bar' 
            ? '.sa-admin-bar-items-list' 
            : '.sa-menu-items-list';
            
        document.querySelectorAll(`${selector} .sa-menu-item`).forEach(item => {
            const checkbox = item.querySelector('label > input[type="checkbox"]');
            if (checkbox) {
                this.handleParentChildCheckboxes(checkbox);
            }
        });
    }

    updateCurrentRoleIndicator(name) {
        this.currentRoleSpan.style.opacity = '0';
        setTimeout(() => {
            this.currentRoleSpan.textContent = `${simplifyAdmin.strings.editing} ${name}`;
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
            formData.append('nonce', simplifyAdmin.nonce);
            formData.append('tab', this.currentTab);
            
            if (role) {
                formData.append('role', role);
            }
            if (userId) {
                formData.append('user_id', userId);
            }
            
            const response = await fetch(simplifyAdmin.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                if (userId && data.data.role && (!data.data.settings || Object.keys(data.data.settings).length === 0)) {
                    return this.loadSettings(data.data.role);
                }

                const container = this.currentTab === 'menu-items'
                    ? document.querySelector('.sa-menu-items-list')
                    : document.querySelector('.sa-admin-bar-items-list');

                container.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.indeterminate = false;
                });

                const settings = data.data.settings || data.data;
                if (settings) {
                    Object.keys(settings).forEach(key => {
                        const checkbox = container.querySelector(`input[name="sa_settings[${key}]"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }

                setTimeout(() => this.initializeCheckboxes(), 0);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        } finally {
            this.hideLoading();
        }
    }

    updateTabLinks(selectedRole = null, selectedUser = null) {
        const tabLinks = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
        const baseUrl = window.location.href.split('?')[0];
        const urlParams = new URLSearchParams();
        
        urlParams.set('page', 'simplify-admin');
        urlParams.set('_wpnonce', simplifyAdmin.nonce);
        
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
                if (input.checked) {
                    this.currentRole = input.value;
                    this.currentRoleName = input.nextElementSibling.textContent.trim();
                    this.currentUser = null;
                    this.currentUserName = '';
                    
                    // Uncheck any selected user
                    this.userInputs.forEach(userInput => {
                        userInput.checked = false;
                        userInput.closest('li').classList.remove('active');
                    });
                    
                    // Update URL parameters while preserving the tab parameter
                    const url = new URL(window.location.href);
                    url.searchParams.set('selected_role', this.currentRole);
                    url.searchParams.delete('selected_user');
                    url.searchParams.set('_wpnonce', simplifyAdmin.nonce);
                    if (!url.searchParams.has('tab')) {
                        url.searchParams.set('tab', this.currentTab);
                    }
                    window.history.pushState({}, '', url);
                    
                    // Update tab navigation links
                    this.updateTabLinks(this.currentRole);
                    
                    this.updateCurrentRoleIndicator(this.currentRoleName);
                    this.loadSettings(this.currentRole);
                    
                    // Update active class
                    document.querySelectorAll('.sa-roles-list li').forEach(li => {
                        li.classList.remove('active');
                    });
                    input.closest('li').classList.add('active');
                }
            });
        });
    }

    initializeUserListeners() {
        this.userInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    this.currentUser = input.value;
                    this.currentUserName = input.nextElementSibling.textContent.split('(')[0].trim();
                    this.currentRole = null;
                    this.currentRoleName = '';
                    
                    // Uncheck any selected role
                    this.roleInputs.forEach(roleInput => {
                        roleInput.checked = false;
                        roleInput.closest('li').classList.remove('active');
                    });
                    
                    // Update URL parameters while preserving the tab parameter
                    const url = new URL(window.location.href);
                    url.searchParams.set('selected_user', this.currentUser);
                    url.searchParams.delete('selected_role');
                    url.searchParams.set('_wpnonce', simplifyAdmin.nonce);
                    if (!url.searchParams.has('tab')) {
                        url.searchParams.set('tab', this.currentTab);
                    }
                    window.history.pushState({}, '', url);
                    
                    // Update tab navigation links
                    this.updateTabLinks(null, this.currentUser);
                    
                    this.updateCurrentRoleIndicator(this.currentUserName);
                    this.loadSettings(null, this.currentUser);
                    
                    // Update active class
                    document.querySelectorAll('.sa-users-list li').forEach(li => {
                        li.classList.remove('active');
                    });
                    input.closest('li').classList.add('active');
                }
            });
        });
    }

    initializeUserSearch() {
        if (this.userSearchInput) {
            this.userSearchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const userItems = document.querySelectorAll('.sa-users-list li');
                
                userItems.forEach(item => {
                    const userName = item.querySelector('span').textContent.toLowerCase();
                    item.style.display = userName.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    }

    initializeTabListeners() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.currentTab = new URL(e.target.href).searchParams.get('tab');
                this.loadSettings(this.currentRole);
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new AdminMenuManager();
});