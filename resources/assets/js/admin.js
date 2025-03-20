import '../scss/admin.scss';

class AdminMenuManager {
    constructor() {
        this.form = document.getElementById('simplify-admin-form');
        this.roleInputs = this.form.querySelectorAll('input[name="selected_role"]');
        this.checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
        this.currentRoleSpan = document.querySelector('.sa-current-role');
        this.currentRole = this.getCheckedRole();
        this.currentRoleName = this.getCheckedRoleName();
        this.currentTab = this.form.dataset.currentTab;

        this.init();
    }

    init() {
        // Set initial active state
        const checkedRole = Array.from(this.roleInputs).find(input => input.checked);
        if (checkedRole) {
            checkedRole.closest('li').classList.add('active');
        }

        // Initialize event listeners
        this.initializeRoleListeners();
        this.initializeTabListeners();
        this.initializeCheckboxes();

        // Load initial settings
        this.loadRoleSettings(this.currentRole);
        this.updateCurrentRoleIndicator(this.currentRoleName);
    }

    getCheckedRole() {
        const checkedInput = Array.from(this.roleInputs).find(input => input.checked);
        return checkedInput ? checkedInput.value : null;
    }

    getCheckedRoleName() {
        const checkedInput = Array.from(this.roleInputs).find(input => input.checked);
        return checkedInput ? checkedInput.nextElementSibling.textContent.trim() : '';
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

    updateCurrentRoleIndicator(roleName) {
        this.currentRoleSpan.style.opacity = '0';
        setTimeout(() => {
            this.currentRoleSpan.textContent = `${simplifyAdmin.strings.editing} ${roleName}`;
            this.currentRoleSpan.style.opacity = '1';
        }, 200);
    }

    async loadRoleSettings(role) {
        try {
            const response = await fetch(simplifyAdmin.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'load_role_settings',
                    role: role,
                    tab: this.currentTab,
                    nonce: simplifyAdmin.nonce
                })
            });

            const data = await response.json();
            
            if (data.success) {
                const container = this.currentTab === 'menu-items'
                    ? document.querySelector('.sa-menu-items-list')
                    : document.querySelector('.sa-admin-bar-items-list');

                // Reset all checkboxes
                container.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.indeterminate = false;
                });

                // Update checkboxes based on saved settings
                if (data.data) {
                    Object.keys(data.data).forEach(key => {
                        const checkbox = container.querySelector(`input[name="sa_settings[${key}]"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }

                // Re-initialize checkbox behavior
                setTimeout(() => this.initializeCheckboxes(), 0);
            }
        } catch (error) {
            console.error('Error loading role settings:', error);
        }
    }

    initializeRoleListeners() {
        this.roleInputs.forEach(input => {
            input.addEventListener('change', () => {
                this.currentRole = input.value;
                this.currentRoleName = input.nextElementSibling.textContent.trim();
                
                this.updateCurrentRoleIndicator(this.currentRoleName);
                this.loadRoleSettings(this.currentRole);
                
                // Update active class
                document.querySelectorAll('.sa-roles-list li').forEach(li => {
                    li.classList.remove('active');
                });
                input.closest('li').classList.add('active');
            });
        });
    }

    initializeTabListeners() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.currentTab = new URL(e.target.href).searchParams.get('tab');
                this.loadRoleSettings(this.currentRole);
            });
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new AdminMenuManager();
}); 