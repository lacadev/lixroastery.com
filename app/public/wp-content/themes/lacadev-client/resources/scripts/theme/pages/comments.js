import Swal from 'sweetalert2';

export const initCommentForm = () => {
    const form = document.getElementById('commentform');
    if (!form) return;

    const submitBtn = form.querySelector('input[type="submit"]');

    // Helper: Get theme colors
    const getThemeColors = () => ({
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
    });

    form.addEventListener('submit', function (e) {
        let isValid = true;
        let errorMessage = '';

        const comment = form.querySelector('#comment').value.trim();
        const authorField = form.querySelector('#author');
        const emailField = form.querySelector('#email');

        // Check required fields based on whether they exist (logged in users might not have them)
        if (!comment) {
            isValid = false;
            errorMessage = 'Vui lòng nhập nội dung bình luận.';
        } else if (authorField && authorField.hasAttribute('required') && !authorField.value.trim()) {
            isValid = false;
            errorMessage = 'Vui lòng nhập tên của bạn.';
        } else if (emailField && emailField.hasAttribute('required') && !emailField.value.trim()) {
            isValid = false;
            errorMessage = 'Vui lòng nhập email của bạn.';
        } else if (emailField && emailField.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
            isValid = false;
            errorMessage = 'Email không hợp lệ.';
        }

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                title: 'Lỗi',
                text: errorMessage,
                icon: 'warning',
                confirmButtonText: 'Đóng',
                ...getThemeColors()
            });
            return;
        }

        // Add loading state
        submitBtn.value = 'Đang gửi...';
        submitBtn.disabled = true;

        // Collect form data
        const formData = new FormData(form);

        // Append action for WordPress ajax handlers if needed, though default comments post to wp-comments-post.php.
        // Let's use fetch to submit asynchronously instead of default page reload
        e.preventDefault();

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                Swal.fire({
                    title: 'Thành công!',
                    text: 'Bình luận của bạn đã được gửi.',
                    icon: 'success',
                    confirmButtonText: 'Đóng',
                    ...getThemeColors()
                }).then(() => {
                    // Reset form and UI
                    form.reset();
                    submitBtn.value = 'Gửi bình luận';
                    submitBtn.disabled = false;
                    form.classList.remove('is-expanded');
                    
                    // Reload page to show new comment, or handle DOM update here if preferred.
                    window.location.reload();
                });
            } else {
                // If WP rejects comment (e.g. spam, duplicate) it usually returns 409 Conflict or 500 error page
                response.text().then(text => {
                    let errText = 'Có lỗi xảy ra khi gửi bình luận. Vui lòng thử lại sau.';
                    // Basic parsing of WP error page for actual reason if possible, otherwise generic generic
                    if (text.includes('<p>')) {
                        const match = text.match(/<p>(.*?)<\/p>/);
                        if (match && match[1]) {
                             // strip tags
                             errText = match[1].replace(/(<([^>]+)>)/gi, "");
                        }
                    }

                    Swal.fire({
                        title: '✕ Thất bại',
                        text: errText,
                        icon: 'error',
                        confirmButtonText: 'Đã hiểu',
                        ...getThemeColors()
                    });
                    
                    submitBtn.value = 'Gửi bình luận';
                    submitBtn.disabled = false;
                });
            }
        })
        .catch(err => {
            console.error('Comment submission error:', err);
            Swal.fire({
                title: '✕ Lỗi kết nối',
                text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra internet.',
                icon: 'error',
                confirmButtonText: 'Đã hiểu',
                ...getThemeColors()
            });
            submitBtn.value = 'Gửi bình luận';
            submitBtn.disabled = false;
        });
    });
};
