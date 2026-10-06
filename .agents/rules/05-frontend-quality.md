# 🎨 FRONTEND & UI/UX QUALITY GUIDELINES

## 1. CSS & Styling Standards
* **SCSS & BEM Naming**:
  * Sử dụng phương pháp đặt tên BEM (`.block-name`, `.block-name__element`, `.block-name__element--modifier`).
  * Tránh nesting quá 3 cấp trong SCSS.
  * Tận dụng utility classes của Tailwind CSS khi render HTML layout.
* **Responsive Design**:
  * Mobile-first hoặc Desktop-down nhất quán theo hệ thống breakpoint chuẩn: Mobile (<768px), Tablet (768px - 1024px), Desktop (>1024px), Wide (>1280px).
  * Kiểm tra hiển thị tốt trên mọi kích thước màn hình, không bị tràn thanh cuộn ngang (`overflow-x: hidden`).

## 2. JavaScript Best Practices (Modern ES6+)
* **NO jQuery**: Tuyệt đối không dùng thư viện jQuery. Sử dụng 100% Vanilla JavaScript ES6+ (`document.querySelector`, `querySelectorAll`, `addEventListener`, `fetch()`, `async/await`, `FormData`).
* **Modular JavaScript**: Viết JS dạng ES6 Module trong `resources/scripts/theme/` hoặc bên trong custom block build.
* **Thư viện bên thứ 3**: Sử dụng thư viện đã có trong bundle (Swiper 9 cho slider, GSAP / AOS cho animation, Fancybox cho lightbox modal). Tránh thêm thư viện ngoài làm phình bundle.

## 3. Accessibility (A11y) & Semantic HTML5
* **Semantic HTML**: Bắt buộc sử dụng đúng thẻ ngữ nghĩa (`<main>`, `<article>`, `<section>`, `<header>`, `<footer>`, `<nav>`, `<aside>`).
* **Tiêu đề (Headings)**: Mỗi trang chỉ có DUY NHẤT một thẻ `<h1>`. Phân cấp tiêu đề hợp lý (`<h2>` -> `<h3>` -> `<h4>`).
* **Forms**: Mọi thẻ `<input>`, `<textarea>`, `<select>` phải có `<label>` tương ứng (có thể dùng class `.screen-reader-text` nếu muốn ẩn về mặt thị giác nhưng vẫn đọc được qua Screen Reader).
* **Nút bấm & Links**: Các icon buttons không có chữ bắt buộc phải có thuộc tính `aria-label="Mô tả hành động"`.
* **Keyboard Navigation**: Các modal, popup drawer, menu overlay phải có cơ chế đóng khi bấm phím `Esc` và bẫy focus (focus trapping).
