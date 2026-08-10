<!DOCTYPE html>
<html lang="vi">

<head>
    <!-- Link to Main Theme CSS (where _404.scss is compiled) -->
    <link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/../dist/styles/theme.css'; ?>">

    <style>
        /* Fallback Styles just in case CSS fails to load or for critical rendering */
        body {
            margin: 0;
            background: #fff;
            overflow: hidden;
        }
    </style>
</head>

<body class="maintenance-body">

    <div class="lang-switch">
        <input type="checkbox" id="lang-toggle" class="toggle-checkbox" onchange="toggleLang(this)">
        <label for="lang-toggle" class="toggle-label">
            <div class="toggle-knob">
                <span class="knob-text">VI</span>
            </div>
        </label>
    </div>

    <!-- Stars generation by JS -->
    <div class="stars" id="stars"></div>


    <!-- Main Content -->
    <div class="content-container">

        <!-- Illustration -->
        <div class="illustration">
            <div class="moon"></div>
            <div class="ground"></div>
            <!-- Tent -->
            <div class="tent">
                <div class="tent-door"></div>
            </div>
            <!-- Fire -->
            <div class="fire-pit"></div>
            <div class="wood"></div>
            <div class="wood w2"></div>
            <div class="fire"></div>
        </div>

        <!-- Text -->
        <h1 id="title">Trạm Dừng Nghỉ</h1>
        <p id="desc">Hành trình nào cũng cần trạm dừng nghỉ. <br> La Cà Dev đang tạm chill và nâng cấp bản thân với những nội dung, hình ảnh mới.</p>

        <!-- Socials -->
        <div class="socials">
            <!-- Github -->
            <a href="https://github.com/moomsdev" target="_blank" title="Github">Github</a>
            <!-- Facebook -->
            <a href="https://www.facebook.com/lacadev.94/" target="_blank" title="Facebook">Facebook</a>
            <!-- Email -->
            <a href="mailto:mooms.dev@gmail.com" title="Email">Gmail</a>
            <!-- Zalo -->
            <a href="https://zalo.me/0989646766" target="_blank" title="Zalo: 0989646766">Zalo</a>
        </div>

        <!-- Lang Switcher -->
       
    </div>

    <footer>&copy; 2025 La Cà Dev</footer>

    <script>
        // Star Generation
        const starsContainer = document.getElementById('stars');
        for (let i = 0; i < 50; i++) {
            const star = document.createElement('div');
            star.classList.add('star');
            const x = Math.random() * 100;
            const y = Math.random() * 80; // Keep in upper 80%
            const size = Math.random() * 2 + 1;
            const delay = Math.random() * 4;

            star.style.left = `${x}%`;
            star.style.top = `${y}%`;
            star.style.width = `${size}px`;
            star.style.height = `${size}px`;
            star.style.animationDelay = `${delay}s`;

            starsContainer.appendChild(star);
        }

        // Language Logic
        const translations = {
            vi: {
                title: "Trạm Dừng Nghỉ",
                desc: "Hành trình nào cũng cần trạm dừng nghỉ. <br> La Cà Dev đang tạm chill và nâng cấp bản thân với những nội dung, hình ảnh mới.",
            },
            en: {
                title: "Rest Stop",
                desc: "Every journey needs a rest stop. <br> La Ca Dev is chilling for a bit to upgrade with fresh content and visuals.",
            }
        };

        let isVI = true;

        function detectLanguage() {
            const userLang = navigator.language || navigator.userLanguage;
            isVI = userLang.toLowerCase().startsWith('vi');

            // Sync Toggle State
            const checkbox = document.getElementById('lang-toggle');
            if (checkbox) {
                checkbox.checked = !isVI; // Checked = EN, Unchecked = VI
            }
            updateKnobText();
            updateContent();
        }

        function toggleLang(checkbox) {
            isVI = !checkbox.checked;
            updateKnobText();
            updateContent();
        }

        function updateKnobText() {
            const knobText = document.querySelector('.knob-text');
            if (knobText) {
                knobText.textContent = isVI ? 'VI' : 'EN';
            }
        }

        function updateContent() {
            const lang = isVI ? translations.vi : translations.en;

            const title = document.getElementById('title');
            const desc = document.getElementById('desc');

            if (title) title.innerHTML = lang.title;
            if (desc) desc.innerHTML = lang.desc;
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', detectLanguage);
    </script>
</body>

</html>