// Main JavaScript for UI enhancements

// Suppress Cloudflare Insights errors (blocked by ad blockers)
window.addEventListener('error', function(e) {
    if (e.message && e.message.includes('cloudflare') || 
        e.filename && e.filename.includes('cloudflare') ||
        e.filename && e.filename.includes('beacon')) {
        e.preventDefault();
        return true;
    }
}, true);

document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Form validation enhancement
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // ตรวจสอบว่า form นี้มี validation แล้วหรือยัง (เช่น register form)
            if (form.id === 'registerForm') {
                // ให้ register form จัดการ validation เอง
                return;
            }
            
            const inputs = form.querySelectorAll('input[required]');
            const selects = form.querySelectorAll('select[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    // Removed red border - just show validation message
                }
            });
            
            selects.forEach(select => {
                if (!select.value || select.value === '') {
                    isValid = false;
                    // Removed red border - just show validation message
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    
    // Ensure select elements can change properly - fix for form-group select elements
    document.querySelectorAll('select.form-group.input, select[class*="form-group"]').forEach(select => {
        // Ensure select is not disabled unless explicitly set
        if (select.hasAttribute('disabled') && !select.hasAttribute('data-keep-disabled')) {
            select.removeAttribute('disabled');
        }
        
        // Ensure select is not readonly
        if (select.hasAttribute('readonly')) {
            select.removeAttribute('readonly');
        }
        
        // Remove any inline styles that might block interaction
        if (select.style.pointerEvents === 'none') {
            select.style.pointerEvents = 'auto';
        }
    });

    // Password confirmation validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordInput = document.getElementById('password');
    
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
                this.style.borderColor = '#ef4444';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '';
            }
        });
    }

    // Add loading state to buttons
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const form = this.closest('form');
            if (form && form.checkValidity()) {
                // อย่า disable ปุ่มทันที ให้ form submit ก่อน
                setTimeout(() => {
                    this.style.opacity = '0.7';
                    this.style.cursor = 'wait';
                }, 100);
            }
        });
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});

// User Info Modal Functions
function openUserInfoModal() {
    // Close any other open modals first
    const troubleshootingModal = document.getElementById('troubleshootingModal');
    if (troubleshootingModal && (troubleshootingModal.classList.contains('modal-show') || troubleshootingModal.style.display === 'flex')) {
        troubleshootingModal.classList.remove('modal-show');
        troubleshootingModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Close user info modal if already open
    const existingModal = document.getElementById('userInfoModal');
    if (existingModal && (existingModal.classList.contains('modal-show') || existingModal.style.display === 'flex')) {
        existingModal.classList.remove('modal-show');
        existingModal.style.display = 'none';
    }
    
    // Wait a bit to ensure modal is closed
    setTimeout(() => {
        const modal = document.getElementById('userInfoModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Reset to view mode when opening
            cancelEditMode();
            cancelChangePasswordMode();
            // Add animation
            setTimeout(() => {
                modal.classList.add('modal-show');
            }, 10);
        }
    }, 50);
}

// Open User Info Modal in Edit Mode
function openUserInfoModalForEdit() {
    // Close any other open modals first
    const troubleshootingModal = document.getElementById('troubleshootingModal');
    if (troubleshootingModal && (troubleshootingModal.classList.contains('modal-show') || troubleshootingModal.style.display === 'flex')) {
        troubleshootingModal.classList.remove('modal-show');
        troubleshootingModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Close user info modal if already open
    const existingModal = document.getElementById('userInfoModal');
    if (existingModal && (existingModal.classList.contains('modal-show') || existingModal.style.display === 'flex')) {
        existingModal.classList.remove('modal-show');
        existingModal.style.display = 'none';
    }
    
    // Wait a bit to ensure modal is closed
    setTimeout(() => {
        const modal = document.getElementById('userInfoModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Reset to view mode first
            cancelEditMode();
            cancelChangePasswordMode();
            // Add animation
            setTimeout(() => {
                modal.classList.add('modal-show');
                // Switch to edit mode after modal is shown
                setTimeout(() => {
                    toggleEditMode();
                }, 100);
            }, 10);
        }
    }, 50);
}

function closeUserInfoModal() {
    const modal = document.getElementById('userInfoModal');
    if (modal) {
        modal.classList.remove('modal-show');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            // Reset to view mode when closing
            cancelEditMode();
            cancelChangePasswordMode();
        }, 300);
    }
    // Close any other open modals
    const troubleshootingModal = document.getElementById('troubleshootingModal');
    if (troubleshootingModal && troubleshootingModal.classList.contains('modal-show')) {
        closeTroubleshootingModal();
    }
}

// Toggle between view and edit mode
function toggleEditMode() {
    const viewMode = document.getElementById('userInfoView');
    const editMode = document.getElementById('userInfoEdit');
    const changePasswordMode = document.getElementById('changePasswordMode');
    const viewButtons = document.getElementById('viewModeButtons');
    const editButtons = document.getElementById('editModeButtons');
    const changePasswordButtons = document.getElementById('changePasswordModeButtons');
    const errorMsg = document.getElementById('edit-error-message');
    const successMsg = document.getElementById('edit-success-message');
    
    if (viewMode && editMode && viewButtons && editButtons) {
        // Hide change password mode if visible
        if (changePasswordMode) changePasswordMode.style.display = 'none';
        if (changePasswordButtons) changePasswordButtons.style.display = 'none';
        
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        viewButtons.style.display = 'none';
        editButtons.style.display = 'flex';
        
        // Clear messages
        if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
        }
        if (successMsg) {
            successMsg.style.display = 'none';
            successMsg.textContent = '';
        }
        
        // Focus on first input (skip disabled and readonly fields)
        const firstInput = editMode.querySelector('input:not([disabled]):not([readonly])');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

// Cancel edit mode and return to view mode
function cancelEditMode() {
    const viewMode = document.getElementById('userInfoView');
    const editMode = document.getElementById('userInfoEdit');
    const changePasswordMode = document.getElementById('changePasswordMode');
    const viewButtons = document.getElementById('viewModeButtons');
    const editButtons = document.getElementById('editModeButtons');
    const changePasswordButtons = document.getElementById('changePasswordModeButtons');
    const errorMsg = document.getElementById('edit-error-message');
    const successMsg = document.getElementById('edit-success-message');
    const form = document.getElementById('editUserForm');
    
    if (viewMode && editMode && viewButtons && editButtons) {
        // Hide change password mode if visible
        if (changePasswordMode) changePasswordMode.style.display = 'none';
        if (changePasswordButtons) changePasswordButtons.style.display = 'none';
        
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
        viewButtons.style.display = 'flex';
        editButtons.style.display = 'none';
        
        // Clear messages
        if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
        }
        if (successMsg) {
            successMsg.style.display = 'none';
            successMsg.textContent = '';
        }
        
        // Reset form (optional - reload original values)
        if (form) {
            form.reset();
        }
    }
}

// Toggle change password mode
function toggleChangePasswordMode() {
    const viewMode = document.getElementById('userInfoView');
    const editMode = document.getElementById('userInfoEdit');
    const changePasswordMode = document.getElementById('changePasswordMode');
    const viewButtons = document.getElementById('viewModeButtons');
    const editButtons = document.getElementById('editModeButtons');
    const changePasswordButtons = document.getElementById('changePasswordModeButtons');
    const errorMsg = document.getElementById('password-error-message');
    const successMsg = document.getElementById('password-success-message');
    
    if (viewMode && changePasswordMode && viewButtons && changePasswordButtons) {
        // Hide edit mode if visible
        if (editMode) editMode.style.display = 'none';
        if (editButtons) editButtons.style.display = 'none';
        
        viewMode.style.display = 'none';
        changePasswordMode.style.display = 'block';
        viewButtons.style.display = 'none';
        changePasswordButtons.style.display = 'flex';
        
        // Clear messages
        if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
        }
        if (successMsg) {
            successMsg.style.display = 'none';
            successMsg.textContent = '';
        }
        
        // Focus on first input
        const firstInput = changePasswordMode.querySelector('input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

// Cancel change password mode and return to view mode
function cancelChangePasswordMode() {
    const viewMode = document.getElementById('userInfoView');
    const editMode = document.getElementById('userInfoEdit');
    const changePasswordMode = document.getElementById('changePasswordMode');
    const viewButtons = document.getElementById('viewModeButtons');
    const editButtons = document.getElementById('editModeButtons');
    const changePasswordButtons = document.getElementById('changePasswordModeButtons');
    const errorMsg = document.getElementById('password-error-message');
    const successMsg = document.getElementById('password-success-message');
    const form = document.getElementById('changePasswordForm');
    
    if (viewMode && changePasswordMode && viewButtons && changePasswordButtons) {
        // Hide edit mode if visible
        if (editMode) editMode.style.display = 'none';
        if (editButtons) editButtons.style.display = 'none';
        
        viewMode.style.display = 'block';
        changePasswordMode.style.display = 'none';
        viewButtons.style.display = 'flex';
        changePasswordButtons.style.display = 'none';
        
        // Clear messages
        if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
        }
        if (successMsg) {
            successMsg.style.display = 'none';
            successMsg.textContent = '';
        }
        
        // Reset form
        if (form) {
            form.reset();
        }
    }
}

// Update password
function updatePassword(event) {
    if (event) {
        event.preventDefault();
    }
    
    const form = document.getElementById('changePasswordForm');
    if (!form) return;
    
    const errorMsg = document.getElementById('password-error-message');
    const successMsg = document.getElementById('password-success-message');
    const submitBtn = document.querySelector('#changePasswordModeButtons .btn-primary');
    const passwordMatchMsg = document.getElementById('password-match-message');
    
    // Clear previous messages
    if (errorMsg) {
        errorMsg.style.display = 'none';
        errorMsg.textContent = '';
    }
    if (successMsg) {
        successMsg.style.display = 'none';
        successMsg.textContent = '';
    }
    if (passwordMatchMsg) {
        passwordMatchMsg.style.display = 'none';
        passwordMatchMsg.textContent = '';
    }
    
    // Get form data
    const formData = new FormData(form);
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_new_password');
    
    // Validate password match
    if (newPassword !== confirmPassword) {
        if (passwordMatchMsg) {
            passwordMatchMsg.textContent = '⚠️ รหัสผ่านใหม่ไม่ตรงกัน';
            passwordMatchMsg.style.display = 'block';
        }
        if (errorMsg) {
            errorMsg.textContent = 'รหัสผ่านใหม่ไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
            errorMsg.style.display = 'block';
        }
        return false;
    }
    
    // Validate password length
    if (newPassword.length < 6) {
        if (errorMsg) {
            errorMsg.textContent = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
            errorMsg.style.display = 'block';
        }
        return false;
    }
    
    // Disable submit button
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังบันทึก...';
    }
    
    // Send AJAX request
    fetch('update_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            if (successMsg) {
                successMsg.textContent = data.message || 'เปลี่ยนรหัสผ่านสำเร็จ';
                successMsg.style.display = 'block';
            }
            
            // Auto close after 2 seconds or switch to view mode
            setTimeout(() => {
                cancelChangePasswordMode();
                if (successMsg) {
                    successMsg.style.display = 'none';
                }
            }, 2000);
        } else {
            // Show error message
            if (errorMsg) {
                errorMsg.textContent = data.message || 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                errorMsg.style.display = 'block';
            }
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'บันทึก';
        }
    })
    .catch(error => {
        if (errorMsg) {
            errorMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
            errorMsg.style.display = 'block';
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'บันทึก';
        }
    });
}

// Real-time password match checking for change password form
document.addEventListener('DOMContentLoaded', function() {
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-new-password');
        const passwordMatchMsg = document.getElementById('password-match-message');
        
        function checkPasswordMatch() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length === 0) {
                if (passwordMatchMsg) {
                    passwordMatchMsg.style.display = 'none';
                }
                if (confirmPasswordInput) {
                    confirmPasswordInput.style.borderColor = '';
                }
                return true;
            }
            
            if (newPassword !== confirmPassword) {
                if (passwordMatchMsg) {
                    passwordMatchMsg.textContent = '⚠️ รหัสผ่านใหม่ไม่ตรงกัน';
                    passwordMatchMsg.style.display = 'block';
                    passwordMatchMsg.style.color = 'var(--error-color)';
                }
                if (confirmPasswordInput) {
                    confirmPasswordInput.style.borderColor = 'var(--error-color)';
                }
                return false;
            } else {
                if (passwordMatchMsg) {
                    passwordMatchMsg.textContent = '✓ รหัสผ่านตรงกัน';
                    passwordMatchMsg.style.display = 'block';
                    passwordMatchMsg.style.color = 'var(--success-color)';
                }
                if (confirmPasswordInput) {
                    confirmPasswordInput.style.borderColor = 'var(--success-color)';
                }
                return true;
            }
        }
        
        if (newPasswordInput && confirmPasswordInput) {
            newPasswordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
    }
});

// Update user profile
function updateUserProfile(event) {
    if (event) {
        event.preventDefault();
    }
    
    const form = document.getElementById('editUserForm');
    if (!form) return;
    
    const errorMsg = document.getElementById('edit-error-message');
    const successMsg = document.getElementById('edit-success-message');
    const submitBtn = document.querySelector('#editModeButtons .btn-primary');
    
    // Clear previous messages
    if (errorMsg) {
        errorMsg.style.display = 'none';
        errorMsg.textContent = '';
    }
    if (successMsg) {
        successMsg.style.display = 'none';
        successMsg.textContent = '';
    }
    
    // Get form data
    const formData = new FormData(form);
    const fullNameInput = form.querySelector('#edit-fullname');
    const fullName = fullNameInput ? fullNameInput.value.trim() : '';
    const phoneInput = form.querySelector('#edit-phone');
    const phone = phoneInput ? phoneInput.value.trim() : '';
    
    // Validate phone number if provided
    if (phone) {
        const phoneDigits = phone.replace(/\D/g, '');
        if (phoneDigits.length < 9 || phoneDigits.length > 10) {
            if (errorMsg) {
                errorMsg.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
                errorMsg.style.display = 'block';
            }
            if (phoneInput) {
                phoneInput.focus();
                phoneInput.style.borderColor = 'var(--error-color)';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'บันทึก';
            }
            return;
        }
        // Remove dashes before submit
        phoneInput.value = phoneDigits;
    }
    
    // Validate full name (English only)
    if (fullName && !/^[a-zA-Z\s]+$/.test(fullName)) {
        if (errorMsg) {
            errorMsg.textContent = 'ชื่อ-นามสกุลต้องเป็นภาษาอังกฤษเท่านั้น (A-Z, a-z)';
            errorMsg.style.display = 'block';
        }
        if (fullNameInput) {
            fullNameInput.focus();
            fullNameInput.style.borderColor = 'var(--error-color)';
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'บันทึก';
        }
        return;
    }
    
    // Disable submit button
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังบันทึก...';
    }
    
    // Send AJAX request
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            if (successMsg) {
                successMsg.textContent = data.message || 'อัพเดทข้อมูลสำเร็จ';
                successMsg.style.display = 'block';
            }
            
            // Update view mode with new data
            const viewEmail = document.getElementById('view-email');
            const viewFullname = document.getElementById('view-fullname');
            const viewPhone = document.getElementById('view-phone');
            const viewDepartment = document.getElementById('view-department');
            
            if (viewEmail && data.user) {
                viewEmail.textContent = data.user.email;
            }
            if (viewFullname && data.user) {
                viewFullname.textContent = data.user.full_name || '-';
            }
            if (viewPhone && data.user) {
                const phone = data.user.phone || '-';
                if (phone !== '-') {
                    viewPhone.textContent = formatPhoneNumber(phone) || phone;
                } else {
                    viewPhone.textContent = phone;
                }
            }
            if (viewDepartment && data.user && data.user.department) {
                viewDepartment.textContent = data.user.department || '-';
            }
            
            // Update sidebar email
            const sidebarEmail = document.getElementById('sidebar-email');
            if (sidebarEmail && data.user) {
                sidebarEmail.textContent = data.user.email;
            }
            
            // Update sidebar fullname
            const sidebarFullname = document.getElementById('sidebar-fullname');
            const sidebarFullnameContainer = document.getElementById('sidebar-fullname-container');
            if (data.user) {
                // Store current value before update
                const currentFullname = sidebarFullname ? sidebarFullname.textContent.trim() : '';
                const isContainerVisible = sidebarFullnameContainer ? sidebarFullnameContainer.style.display !== 'none' : false;
                
                // Use new value if provided and not empty, otherwise keep current
                const newFullname = (data.user.full_name && data.user.full_name.trim() !== '') 
                    ? data.user.full_name 
                    : currentFullname;
                
                if (newFullname && newFullname !== '-') {
                    if (sidebarFullname) {
                        sidebarFullname.textContent = newFullname;
                    }
                    if (sidebarFullnameContainer) {
                        sidebarFullnameContainer.style.display = 'block';
                    }
                } else if (!isContainerVisible) {
                    // Only hide if it wasn't visible before
                    if (sidebarFullnameContainer) {
                        sidebarFullnameContainer.style.display = 'none';
                    }
                }
            }
            
            // Update sidebar phone
            const sidebarPhone = document.getElementById('sidebar-phone');
            const sidebarPhoneContainer = document.getElementById('sidebar-phone-container');
            if (data.user) {
                // Store current value before update
                const currentPhone = sidebarPhone ? sidebarPhone.textContent.trim() : '';
                const isContainerVisible = sidebarPhoneContainer ? sidebarPhoneContainer.style.display !== 'none' : false;
                
                // Use new value if provided and not empty, otherwise keep current
                let newPhone = data.user.phone || '';
                if (!newPhone || newPhone === '-' || newPhone.trim() === '') {
                    newPhone = currentPhone;
                }
                
                if (newPhone && newPhone.trim() !== '' && newPhone !== '-') {
                    const formattedPhone = formatPhoneNumber(newPhone) || newPhone;
                    if (sidebarPhone) {
                        sidebarPhone.textContent = formattedPhone;
                    }
                    if (sidebarPhoneContainer) {
                        sidebarPhoneContainer.style.display = 'block';
                    }
                } else if (!isContainerVisible) {
                    // Only hide if it wasn't visible before
                    if (sidebarPhoneContainer) {
                        sidebarPhoneContainer.style.display = 'none';
                    }
                }
            }
            
            // Update header user display (show full_name if available, otherwise email)
            const headerUserDisplay = document.getElementById('header-user-display');
            if (headerUserDisplay && data.user) {
                if (data.user.full_name) {
                    headerUserDisplay.textContent = data.user.full_name;
                } else if (data.user.email) {
                    headerUserDisplay.textContent = data.user.email;
                }
            }
            
            // Update edit form fields with new data
            const editEmailInput = document.getElementById('edit-email');
            const editFullnameInput = document.getElementById('edit-fullname');
            const editPhoneInput = document.getElementById('edit-phone');
            
            if (editEmailInput && data.user) {
                editEmailInput.value = data.user.email || '';
            }
            if (editFullnameInput && data.user) {
                editFullnameInput.value = data.user.full_name || '';
            }
            if (editPhoneInput && data.user) {
                // Phone is already formatted from server
                editPhoneInput.value = data.user.phone || '';
            }
            
            // Auto close after 2 seconds or switch to view mode
            setTimeout(() => {
                cancelEditMode();
                if (successMsg) {
                    successMsg.style.display = 'none';
                }
            }, 2000);
        } else {
            // Show error message
            if (errorMsg) {
                errorMsg.textContent = data.message || 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล';
                errorMsg.style.display = 'block';
            }
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'บันทึก';
        }
    })
    .catch(error => {
        console.error('Update profile error:', error);
        if (errorMsg) {
            errorMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + (error.message || 'ไม่ทราบสาเหตุ');
            errorMsg.style.display = 'block';
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'บันทึก';
        }
    });
}

// Troubleshooting Modal Functions
function openTroubleshootingModal() {
    // Close any other open modals first
    const userInfoModal = document.getElementById('userInfoModal');
    if (userInfoModal && (userInfoModal.classList.contains('modal-show') || userInfoModal.style.display === 'flex')) {
        userInfoModal.classList.remove('modal-show');
        userInfoModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Close troubleshooting modal if already open
    const existingModal = document.getElementById('troubleshootingModal');
    if (existingModal && (existingModal.classList.contains('modal-show') || existingModal.style.display === 'flex')) {
        existingModal.classList.remove('modal-show');
        existingModal.style.display = 'none';
    }
    
    // Wait a bit to ensure modal is closed
    setTimeout(() => {
        const modal = document.getElementById('troubleshootingModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Add animation
            setTimeout(() => {
                modal.classList.add('modal-show');
                // Initialize accordion sections recursively - always reinitialize
                const content = modal.querySelector('.troubleshooting-content');
                if (content) {
                    // Force reinitialize by resetting first
                    resetAccordionSections(modal);
                    // Then initialize
                    initializeAccordionSections(content);
                }
            }, 10);
        }
    }, 50);
}

function closeTroubleshootingModal() {
    const modal = document.getElementById('troubleshootingModal');
    if (modal) {
        modal.classList.remove('modal-show');
        // Reset all accordion sections when closing modal
        resetAccordionSections(modal);
        // Close all detail sections
        const allDetails = modal.querySelectorAll('.troubleshooting-detail');
        allDetails.forEach(detail => {
            detail.style.display = 'none';
        });
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }
}

// Logout Modal Functions
function openLogoutModal() {
    // Close any other open modals first
    const userInfoModal = document.getElementById('userInfoModal');
    if (userInfoModal && (userInfoModal.classList.contains('modal-show') || userInfoModal.style.display === 'flex')) {
        userInfoModal.classList.remove('modal-show');
        userInfoModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    const troubleshootingModal = document.getElementById('troubleshootingModal');
    if (troubleshootingModal && (troubleshootingModal.classList.contains('modal-show') || troubleshootingModal.style.display === 'flex')) {
        troubleshootingModal.classList.remove('modal-show');
        troubleshootingModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Close logout modal if already open
    const existingModal = document.getElementById('logoutModal');
    if (existingModal && (existingModal.classList.contains('modal-show') || existingModal.style.display === 'flex')) {
        existingModal.classList.remove('modal-show');
        existingModal.style.display = 'none';
    }
    
    // Wait a bit to ensure modal is closed
    setTimeout(() => {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Add animation
            setTimeout(() => {
                modal.classList.add('modal-show');
            }, 10);
        }
    }, 50);
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.remove('modal-show');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }
}

// Show troubleshooting detail
function showTroubleshootingDetail(itemId) {
    const detail = document.getElementById('detail-' + itemId);
    if (!detail) return;
    
    // Close all other details first
    const allDetails = document.querySelectorAll('.troubleshooting-detail');
    allDetails.forEach(d => {
        if (d.id !== 'detail-' + itemId) {
            d.style.display = 'none';
        }
    });
    
    // Toggle current detail
    if (detail.style.display === 'none' || detail.style.display === '') {
        detail.style.display = 'block';
    } else {
        detail.style.display = 'none';
    }
}

// Recursive function to toggle troubleshooting section
function toggleTroubleshootingSection(header, closeNested = false) {
    const section = header.closest('.troubleshooting-section');
    if (!section) return;
    
    const content = section.querySelector('.troubleshooting-content-inner');
    const icon = header.querySelector('.troubleshooting-icon');
    
    if (!content) return;
    
    const isOpen = content.style.maxHeight && content.style.maxHeight !== '0px';
    
    if (isOpen) {
        // Close current section and all nested sections recursively
        closeSectionRecursive(section);
    } else {
        // Open current section
        openSection(section, content, icon);
        
        // Optionally close other sections at the same level
        if (closeNested) {
            closeSiblingSections(section);
        }
    }
}

// Recursive function to close section and all nested sections
function closeSectionRecursive(section) {
    const content = section.querySelector('.troubleshooting-content-inner');
    const header = section.querySelector('.troubleshooting-header');
    const icon = header ? header.querySelector('.troubleshooting-icon') : null;
    
    if (content) {
        // Close current section
        content.style.maxHeight = '0px';
        content.style.opacity = '0';
        if (icon) {
            icon.style.transform = 'rotate(0deg)';
        }
        section.classList.remove('troubleshooting-open');
        
        // Recursively close all nested sections
        const nestedSections = section.querySelectorAll('.troubleshooting-section');
        nestedSections.forEach(nestedSection => {
            if (nestedSection !== section) {
                closeSectionRecursive(nestedSection);
            }
        });
    }
}

// Function to open section
function openSection(section, content, icon) {
    // Set max-height to allow scrolling, use a large value to accommodate all content
    const maxHeight = Math.max(content.scrollHeight, 500) + 'px';
    content.style.maxHeight = maxHeight;
    content.style.opacity = '1';
    if (icon) {
        icon.style.transform = 'rotate(180deg)';
    }
    section.classList.add('troubleshooting-open');
}

// Function to close sibling sections at the same level
function closeSiblingSections(currentSection) {
    const parent = currentSection.parentElement;
    if (!parent) return;
    
    const siblings = parent.querySelectorAll('.troubleshooting-section');
    siblings.forEach(sibling => {
        if (sibling !== currentSection && sibling.closest('.troubleshooting-content-inner') === null) {
            closeSectionRecursive(sibling);
        }
    });
}

// Reset all accordion sections to closed state
function resetAccordionSections(container) {
    if (!container) return;
    
    const sections = container.querySelectorAll('.troubleshooting-section');
    sections.forEach(section => {
        const content = section.querySelector('.troubleshooting-content-inner');
        const header = section.querySelector('.troubleshooting-header');
        const icon = header ? header.querySelector('.troubleshooting-icon') : null;
        
        if (content) {
            content.style.maxHeight = '0px';
            content.style.opacity = '0';
            section.classList.remove('troubleshooting-open');
        }
        
        if (icon) {
            icon.style.transform = 'rotate(0deg)';
        }
        
        // Reset data attribute to allow re-initialization
        if (header) {
            delete header.dataset.accordionInitialized;
        }
    });
}

// Initialize all accordion sections recursively
function initializeAccordionSections(container) {
    if (!container) return;
    
    // First, reset all sections to closed state
    resetAccordionSections(container);
    
    // Remove all existing event listeners by cloning headers
    const sections = container.querySelectorAll('.troubleshooting-section');
    sections.forEach(section => {
        const header = section.querySelector('.troubleshooting-header');
        if (header) {
            // Clone header to remove all event listeners
            const newHeader = header.cloneNode(true);
            header.parentNode.replaceChild(newHeader, header);
            
            // Add new event listener
            newHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleTroubleshootingSection(this, false);
            });
            
            // Mark as initialized
            newHeader.dataset.accordionInitialized = 'true';
        }
    });
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const userInfoModal = document.getElementById('userInfoModal');
    if (userInfoModal) {
        userInfoModal.addEventListener('click', function(e) {
            if (e.target === userInfoModal) {
                closeUserInfoModal();
            }
        });
    }
    
    const troubleshootingModal = document.getElementById('troubleshootingModal');
    if (troubleshootingModal) {
        troubleshootingModal.addEventListener('click', function(e) {
            if (e.target === troubleshootingModal) {
                closeTroubleshootingModal();
            }
        });
    }
    
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                closeLogoutModal();
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (userInfoModal && userInfoModal.style.display === 'flex') {
                closeUserInfoModal();
            }
            if (troubleshootingModal && troubleshootingModal.style.display === 'flex') {
                closeTroubleshootingModal();
            }
            if (logoutModal && logoutModal.style.display === 'flex') {
                closeLogoutModal();
            }
        }
    });
});

// Password toggle function
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '-toggle-icon');
    
    if (input) {
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    }
}

// Format phone number: 000-000-0000 (10 digits) or 000-000-000 (9 digits)
function formatPhoneNumber(phone) {
    if (!phone) return '';
    // Remove all non-digit characters
    const digits = phone.replace(/\D/g, '');
    
    if (digits.length === 10) {
        // Format: 000-000-0000
        return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
    } else if (digits.length === 9) {
        // Format: 000-000-000
        return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
    }
    // Return as is if not 9 or 10 digits
    return digits;
}

// Auto-format phone number input
function setupPhoneAutoFormat(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('input', function(e) {
        const cursorPosition = this.selectionStart;
        const oldValue = this.value;
        const digits = oldValue.replace(/\D/g, '');
        
        // Limit to 10 digits
        const limitedDigits = digits.slice(0, 10);
        
        // Format the phone number
        let formatted = '';
        if (limitedDigits.length <= 3) {
            formatted = limitedDigits;
        } else if (limitedDigits.length <= 6) {
            formatted = limitedDigits.slice(0, 3) + '-' + limitedDigits.slice(3);
        } else {
            formatted = limitedDigits.slice(0, 3) + '-' + limitedDigits.slice(3, 6) + '-' + limitedDigits.slice(6);
        }
        
        // Update value
        this.value = formatted;
        
        // Adjust cursor position
        const newCursorPosition = cursorPosition + (formatted.length - oldValue.length);
        this.setSelectionRange(newCursorPosition, newCursorPosition);
    });
    
    // Remove dashes before form submission
    const form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            input.value = input.value.replace(/\D/g, '');
        });
    }
}

// Initialize phone auto-format on page load
document.addEventListener('DOMContentLoaded', function() {
    // Setup for registration form
    setupPhoneAutoFormat('register_phone');
    
    // Setup for edit phone in dashboard
    setupPhoneAutoFormat('edit-phone');
});

