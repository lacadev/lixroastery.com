/**
 * Editor interaction & content analysis runner.
 * Handles live updates in Gutenberg, Classic editor & Taxonomy screen,
 * focus keywords tag UI, SERP preview device switcher, character/pixel counters,
 * AJAX preview vars resolution for templates, and test renderers.
 */

import { LacaSeoAnalyzer } from './analyzer';

document.addEventListener('DOMContentLoaded', () => {
  const metaboxApp = document.getElementById('laca_seo_metabox_app');
  if (!metaboxApp) return;

  const dataConfig = window.lacaSeoEditorData || {};
  const isTermScreen = Boolean(dataConfig.termId || dataConfig.taxonomy || metaboxApp.hasAttribute('data-term-id'));
  const isProduct = dataConfig.postType === 'product';

  const analyzer = new LacaSeoAnalyzer({
    isPost: dataConfig.postType !== 'page' && !isTermScreen,
    isPage: dataConfig.postType === 'page',
    isProduct: isProduct,
    isTerm: isTermScreen,
    locale: dataConfig.locale || 'en',
    powerWords: dataConfig.powerWords || [],
    hasProductSchema: Boolean(dataConfig.hasProductSchema),
    isReviewEnabled: Boolean(dataConfig.isReviewEnabled),
    parentDomain: dataConfig.homeUrl ? new URL(dataConfig.homeUrl).hostname : '',
  });

  // State
  let focusKeywords = [];
  const hiddenKwInput = document.getElementById('laca_seo_focus_kw_hidden');
  if (hiddenKwInput && hiddenKwInput.value) {
    focusKeywords = hiddenKwInput.value.split(',').map(s => s.trim()).filter(Boolean);
  }

  // Keywords cache map for isKeywordNew AJAX
  const keywordsCheckedCache = new Map();

  // DOM Elements
  const tabs = metaboxApp.querySelectorAll('.laca-seo-tab-btn');
  const tabPanels = metaboxApp.querySelectorAll('.laca-seo-tab-content');
  const titleInput = document.getElementById('laca_seo_title_input');
  const descInput = document.getElementById('laca_seo_desc_input');
  const serpTitle = document.getElementById('laca_serp_title');
  const serpDesc = document.getElementById('laca_serp_desc');
  const serpPath = document.getElementById('laca_serp_path');
  const serpCard = document.getElementById('laca_serp_card');
  const tagsList = document.getElementById('laca_tags_list');
  const kwInput = document.getElementById('laca_kw_type_input');
  const scoreNumber = document.getElementById('laca_score_number');
  const scoreCircle = document.getElementById('laca_score_circle');
  const scoreInput = document.getElementById('laca_seo_score_input');
  const tabScore = document.getElementById('laca_seo_tab_score');
  const titleCounter = document.getElementById('laca_seo_title_counter');
  const descCounter = document.getElementById('laca_seo_desc_counter');

  /* -------------------------------------------------------------
   * 1. Tab Switching
   * ------------------------------------------------------------- */
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');
      tabs.forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      tabPanels.forEach(p => {
        p.classList.remove('active');
        p.hidden = true;
      });

      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      const activePanel = document.getElementById('laca_seo_tab_' + target);
      if (activePanel) {
        activePanel.classList.add('active');
        activePanel.hidden = false;
      }
    });
  });

  /* -------------------------------------------------------------
   * 2. Device Switcher (Desktop / Mobile)
   * ------------------------------------------------------------- */
  const deviceBtns = metaboxApp.querySelectorAll('.laca-serp-btn');
  deviceBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      deviceBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const device = btn.getAttribute('data-device');
      if (serpCard) {
        serpCard.className = `laca-serp-card laca-serp-card--${device}`;
      }
    });
  });

  /* -------------------------------------------------------------
   * 3. Focus Keywords Tag UI (Max 100, Drag & Drop / Order)
   * ------------------------------------------------------------- */
  function renderTags() {
    if (!tagsList) return;
    tagsList.innerHTML = '';
    focusKeywords.forEach((kw, index) => {
      const tag = document.createElement('span');
      tag.className = 'laca-kw-tag' + (index === 0 ? ' laca-kw-tag--primary' : '');
      tag.draggable = true;
      tag.dataset.index = index;
      tag.innerHTML = `
        <span class="laca-kw-text">${escapeHtml(kw)}</span>
        ${index === 0 ? '<span class="laca-kw-badge">Chính</span>' : ''}
        <button type="button" class="laca-kw-remove" data-index="${index}" aria-label="Xoá">&times;</button>
      `;

      tag.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', index);
        tag.classList.add('dragging');
      });
      tag.addEventListener('dragend', () => {
        tag.classList.remove('dragging');
      });
      tag.addEventListener('dragover', (e) => {
        e.preventDefault();
      });
      tag.addEventListener('drop', (e) => {
        e.preventDefault();
        const fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
        const toIndex = index;
        if (!isNaN(fromIndex) && fromIndex !== toIndex) {
          const item = focusKeywords.splice(fromIndex, 1)[0];
          focusKeywords.splice(toIndex, 0, item);
          syncKeywords();
        }
      });

      tagsList.appendChild(tag);
    });

    if (hiddenKwInput) {
      hiddenKwInput.value = focusKeywords.join(', ');
    }
  }

  function syncKeywords() {
    renderTags();
    runLiveAnalysis();
  }

  if (tagsList) {
    tagsList.addEventListener('click', (e) => {
      const removeBtn = e.target.closest('.laca-kw-remove');
      if (removeBtn) {
        const index = parseInt(removeBtn.getAttribute('data-index'), 10);
        focusKeywords.splice(index, 1);
        syncKeywords();
      }
    });
  }

  if (kwInput) {
    kwInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = kwInput.value.trim().replace(/^,+|,+$/g, '');
        if (val && !focusKeywords.includes(val)) {
          if (focusKeywords.length >= 100) {
            alert('Bạn chỉ có thể thêm tối đa 100 từ khoá chính.');
            return;
          }
          focusKeywords.push(val);
          kwInput.value = '';
          syncKeywords();
        }
      } else if (e.key === 'Backspace' && kwInput.value === '' && focusKeywords.length > 0) {
        focusKeywords.pop();
        syncKeywords();
      }
    });
  }

  renderTags();

  /* -------------------------------------------------------------
   * 4. Counters (Characters & Pixels)
   * ------------------------------------------------------------- */
  function measurePixelWidth(text, font = '18px Arial') {
    const canvas = measurePixelWidth.canvas || (measurePixelWidth.canvas = document.createElement('canvas'));
    const context = canvas.getContext('2d');
    context.font = font;
    return Math.round(context.measureText(text).width);
  }

  function updateCounters(renderedTitle = '', renderedDesc = '') {
    if (titleCounter) {
      const text = renderedTitle || (titleInput ? titleInput.value : '');
      const count = text.length;
      const px = measurePixelWidth(text, '18px Arial');
      const valEl = titleCounter.querySelector('.laca-counter-val');
      const pxEl = titleCounter.querySelector('.laca-pixel-val');
      if (valEl) valEl.textContent = count;
      if (pxEl) pxEl.textContent = px;
      titleCounter.className = 'laca-seo-counter' + (count > 60 || px > 580 ? ' is-warning' : (count >= 40 ? ' is-good' : ''));
    }

    if (descCounter) {
      const text = renderedDesc || (descInput ? descInput.value : '');
      const count = text.length;
      const px = measurePixelWidth(text, '13px Arial');
      const valEl = descCounter.querySelector('.laca-counter-val');
      const pxEl = descCounter.querySelector('.laca-pixel-val');
      if (valEl) valEl.textContent = count;
      if (pxEl) pxEl.textContent = px;
      descCounter.className = 'laca-seo-counter' + (count > 160 || px > 960 ? ' is-warning' : (count >= 120 ? ' is-good' : ''));
    }
  }

  if (titleInput) {
    titleInput.addEventListener('input', () => {
      debouncePreviewAndAnalysis();
    });
  }
  if (descInput) {
    descInput.addEventListener('input', () => {
      debouncePreviewAndAnalysis();
    });
  }

  /* -------------------------------------------------------------
   * 5. Media Upload for Facebook / Twitter
   * ------------------------------------------------------------- */
  metaboxApp.querySelectorAll('.laca-upload-media-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-target');
      const frame = wp.media({
        title: 'Chọn hình ảnh chia sẻ mạng xã hội',
        button: { text: 'Sử dụng ảnh này' },
        multiple: false
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        const urlInput = document.getElementById(`laca_seo_${target}_img`);
        const idInput = document.getElementById(`laca_seo_${target}_img_id`);
        const preview = document.getElementById(`laca_${target}_img_preview`);
        const removeBtn = metaboxApp.querySelector(`.laca-remove-media-btn[data-target="${target}"]`);

        if (urlInput) urlInput.value = attachment.url;
        if (idInput) idInput.value = attachment.id;
        if (preview) {
          preview.replaceChildren();
          const img = document.createElement('img');
          img.src = attachment.url;
          img.alt = 'Preview';
          preview.appendChild(img);
        }
        if (removeBtn) removeBtn.style.display = 'inline-block';
      });
      frame.open();
    });
  });

  metaboxApp.querySelectorAll('.laca-remove-media-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-target');
      const urlInput = document.getElementById(`laca_seo_${target}_img`);
      const idInput = document.getElementById(`laca_seo_${target}_img_id`);
      const preview = document.getElementById(`laca_${target}_img_preview`);

      if (urlInput) urlInput.value = '';
      if (idInput) idInput.value = '';
      if (preview) preview.innerHTML = '';
      btn.style.display = 'none';
    });
  });

  const twUseFbCheckbox = document.getElementById('laca_twitter_use_fb');
  const twCustomFields = document.getElementById('laca_twitter_custom_fields');
  if (twUseFbCheckbox && twCustomFields) {
    twUseFbCheckbox.addEventListener('change', () => {
      twCustomFields.style.display = twUseFbCheckbox.checked ? 'none' : 'block';
    });
  }

  metaboxApp.querySelectorAll('.laca-group-header').forEach(header => {
    header.addEventListener('click', () => {
      const group = header.closest('.laca-test-group');
      if (group) {
        group.classList.toggle('is-open');
      }
    });
  });

  /* -------------------------------------------------------------
   * 6. Editor Content Extractor (Gutenberg / Classic / Term)
   * ------------------------------------------------------------- */
  function getEditorContent() {
    // 1. Term screen
    if (isTermScreen) {
      const nameEl = document.getElementById('name');
      const descEl = document.getElementById('description');
      const slugEl = document.getElementById('slug');
      return {
        title: nameEl ? nameEl.value : '',
        content: descEl ? descEl.value : '',
        excerpt: '',
        slug: slugEl ? slugEl.value : '',
        thumbnail: '',
        thumbnailAlt: '',
        isGutenberg: false,
      };
    }

    // 2. Gutenberg Check
    const isBlockEditor = document.body.classList.contains('block-editor-page') &&
      window.wp && wp.data && wp.data.select && wp.data.select('core/editor');

    if (isBlockEditor) {
      const editorSelect = wp.data.select('core/editor');
      const title = editorSelect.getEditedPostAttribute('title') || '';
      const content = editorSelect.getEditedPostContent() || '';
      const excerpt = editorSelect.getEditedPostAttribute('excerpt') || '';
      const slug = editorSelect.getEditedPostAttribute('slug') || '';
      const featId = editorSelect.getEditedPostAttribute('featured_media');
      let thumbnail = '';
      let thumbnailAlt = '';
      if (featId && wp.data.select('core')) {
        const media = wp.data.select('core').getMedia(featId);
        if (media) {
          thumbnail = media.source_url || '';
          thumbnailAlt = media.alt_text || '';
        }
      }
      return {
        title,
        content: content || excerpt,
        excerpt,
        slug,
        thumbnail,
        thumbnailAlt,
        isGutenberg: true,
      };
    }

    // 3. Classic Editor Check
    let content = '';
    if (window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) {
      content = window.tinymce.get('content').getContent();
    } else {
      const txt = document.getElementById('content');
      if (txt) content = txt.value;
    }

    let excerpt = '';
    if (window.tinymce && window.tinymce.get('excerpt') && !window.tinymce.get('excerpt').isHidden()) {
      excerpt = window.tinymce.get('excerpt').getContent();
    } else {
      const excEl = document.getElementById('excerpt');
      if (excEl) excerpt = excEl.value;
    }

    // For products or posts with empty content, fallback to excerpt (matches RM dataCollector behavior)
    if (!content.trim() && excerpt.trim()) {
      content = excerpt;
    }

    const titleEl = document.getElementById('title');
    const title = titleEl ? titleEl.value : '';
    const slugEl = document.getElementById('post_name');
    const slug = slugEl ? slugEl.value : '';

    // Thumbnail from classic editor
    const thumbInput = document.getElementById('_thumbnail_id');
    const thumbImg = document.querySelector('#postimagediv img');
    const thumbnail = (thumbInput && parseInt(thumbInput.value, 10) > 0) || Boolean(thumbImg);
    const thumbnailAlt = thumbImg ? (thumbImg.getAttribute('alt') || '') : '';

    return {
      title,
      content,
      excerpt,
      slug,
      thumbnail: thumbnail ? 'has_thumb' : '',
      thumbnailAlt,
      isGutenberg: false,
    };
  }

  /* -------------------------------------------------------------
   * 7. Template Preview & Live Analysis
   * ------------------------------------------------------------- */
  let renderedTitleCache = '';
  let renderedDescCache = '';
  let previewDebounceTimer = null;

  function debouncePreviewAndAnalysis() {
    clearTimeout(previewDebounceTimer);
    previewDebounceTimer = setTimeout(runFullCycle, 400);
  }

  async function resolvePreviewVars(template, editorState) {
    if (!template) return '';
    // If template has no %vars%, return directly
    if (!template.includes('%')) return template;

    try {
      const body = new URLSearchParams({
        action: 'laca_seo_preview_vars',
        nonce: dataConfig.nonce || '',
        template: template,
        post_id: dataConfig.postId || 0,
        term_id: dataConfig.termId || 0,
        taxonomy: dataConfig.taxonomy || '',
        current_title: editorState.title || '',
        current_excerpt: editorState.excerpt || '',
        current_content: editorState.content || '',
      });

      const resp = await fetch(dataConfig.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const json = await resp.json();
      if (json && json.success && typeof json.data.preview === 'string') {
        return json.data.preview;
      }
    } catch (e) {
      // Ignore network errors in local dev
    }

    // Fallback: replace basic %title%, %sep%, %sitename% locally
    const siteTitle = dataConfig.parentDomain || 'LIX ROASTERY';
    return template
      .replace(/%title%/gi, editorState.title || '')
      .replace(/%term%/gi, editorState.title || '')
      .replace(/%sep%/gi, '-')
      .replace(/%sitename%/gi, siteTitle)
      .replace(/%page%/gi, '')
      .replace(/%excerpt%/gi, editorState.excerpt || '')
      .replace(/%term_description%/gi, editorState.content || '')
      .replace(/\s{2,}/g, ' ')
      .trim();
  }

  async function runFullCycle() {
    const editor = getEditorContent();
    const primaryKeyword = focusKeywords[0] || '';

    // Determine raw template or custom input
    const rawTitle = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : (dataConfig.defaultTitleTpl || '%title% %sep% %sitename%');
    const rawDesc = (descInput && descInput.value.trim()) ? descInput.value.trim() : (dataConfig.defaultDescTpl || '%excerpt%');

    // Resolve via AJAX
    const [resTitle, resDesc] = await Promise.all([
      resolvePreviewVars(rawTitle, editor),
      resolvePreviewVars(rawDesc, editor),
    ]);

    renderedTitleCache = resTitle.trim();
    renderedDescCache = resDesc.trim();

    // Update SERP preview
    if (serpTitle) {
      serpTitle.textContent = renderedTitleCache || editor.title || 'Tiêu đề bài viết';
    }
    if (serpDesc) {
      serpDesc.textContent = renderedDescCache || '';
    }
    if (serpPath && editor.slug) {
      serpPath.textContent = ' › ' + editor.slug;
    }

    updateCounters(renderedTitleCache, renderedDescCache);

    // Keyword is new check
    let isKeywordNew = true;
    if (primaryKeyword) {
      if (keywordsCheckedCache.has(primaryKeyword)) {
        isKeywordNew = keywordsCheckedCache.get(primaryKeyword);
      } else {
        try {
          const kwBody = new URLSearchParams({
            action: 'laca_seo_is_keyword_new',
            nonce: dataConfig.nonce || '',
            keyword: primaryKeyword,
            post_id: dataConfig.postId || 0,
          });
          const kwResp = await fetch(dataConfig.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: kwBody.toString(),
          });
          const kwJson = await kwResp.json();
          if (kwJson && kwJson.success) {
            isKeywordNew = Boolean(kwJson.data.isNew);
            keywordsCheckedCache.set(primaryKeyword, isKeywordNew);
          }
        } catch (e) {
          // Fallback
        }
      }
    }

    // Run analyzer with rendered title and description
    const analysis = analyzer.analyze({
      title: renderedTitleCache || editor.title,
      description: renderedDescCache,
      keyword: primaryKeyword,
      content: editor.content,
      permalink: dataConfig.permalink || window.location.href,
      isKeywordNew: isKeywordNew,
      thumbnail: editor.thumbnail,
      thumbnailAlt: editor.thumbnailAlt,
      images: [],
      videos: [],
    });

    // Update Score Circle & Hidden input
    const score = analysis.score;
    if (scoreNumber) scoreNumber.textContent = score;
    if (scoreInput) scoreInput.value = score;
    if (tabScore) tabScore.textContent = `${score}/100`;

    if (scoreCircle) {
      scoreCircle.className = `laca-score-circle laca-score--${score >= 80 ? 'good' : (score >= 50 ? 'ok' : 'bad')}`;
    }

    // Render results in groups
    if (isTermScreen) {
      renderGroupTests('basic', ['keywordInTitle', 'keywordInMetaDescription', 'keywordInPermalink', 'keywordNotUsed', 'titleStartWithKeyword'], analysis.results);
    } else {
      const basicTests = ['keywordInTitle', 'keywordInMetaDescription', 'keywordInPermalink', 'keywordIn10Percent', 'keywordInContent', 'lengthContent'];
      if (analysis.results && analysis.results.hasProductSchema) {
        basicTests.push('hasProductSchema');
      }

      const additionalTests = ['keywordInSubheadings', 'keywordInImageAlt', 'keywordDensity', 'lengthPermalink', 'linksHasExternals', 'linksNotAllExternals', 'linksHasInternal', 'keywordNotUsed'];
      if (analysis.results && analysis.results.isReviewEnabled) {
        additionalTests.push('isReviewEnabled');
      }

      renderGroupTests('basic', basicTests, analysis.results);
      renderGroupTests('additional', additionalTests, analysis.results);
      renderGroupTests('title', ['titleStartWithKeyword', 'titleSentiment', 'titleHasPowerWords', 'titleHasNumber'], analysis.results);
      renderGroupTests('content', ['contentHasTOC', 'contentHasShortParagraphs', 'contentHasAssets'], analysis.results);
    }
  }

  function runLiveAnalysis() {
    debouncePreviewAndAnalysis();
  }

  function renderGroupTests(groupId, testIds, allResults) {
    const container = document.getElementById(`group_${groupId}_tests`);
    const countBadge = document.getElementById(`count_${groupId}`);
    if (!container) return;

    let passed = 0;
    let total = 0;
    let html = '';

    testIds.forEach(id => {
      const res = allResults[id];
      if (!res) return;
      total++;
      if (res.ok) passed++;

      html += `
        <div class="laca-test-item laca-test-item--${res.ok ? 'pass' : 'fail'}">
          <span class="dashicons ${res.ok ? 'dashicons-yes-alt' : 'dashicons-dismiss'} laca-test-icon"></span>
          <div class="laca-test-info">
            <span class="laca-test-msg">${escapeHtml(res.message)}</span>
            ${res.tooltip ? `<span class="laca-test-tip">${escapeHtml(res.tooltip)}</span>` : ''}
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
    if (countBadge) {
      countBadge.textContent = `${passed}/${total}`;
      countBadge.className = `laca-group-count ${passed === total ? 'is-all-pass' : ''}`;
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Subscribe to Gutenberg changes
  if (window.wp && wp.data && wp.data.subscribe) {
    let prevTitle = '';
    let prevBlocks = null;
    wp.data.subscribe(() => {
      const editorSelect = wp.data.select('core/editor');
      if (!editorSelect) return;
      const title = editorSelect.getEditedPostAttribute('title');
      const blocks = editorSelect.getBlocks();
      if (title !== prevTitle || blocks !== prevBlocks) {
        prevTitle = title;
        prevBlocks = blocks;
        debouncePreviewAndAnalysis();
      }
    });
  }

  // Listen to Classic Editor title changes
  const classicTitleEl = document.getElementById('title') || document.getElementById('name');
  if (classicTitleEl) {
    classicTitleEl.addEventListener('input', () => {
      debouncePreviewAndAnalysis();
    });
  }

  /* -------------------------------------------------------------
   * 7. Multi-Schema Generator Manager (Phase 3B - Vòng 2)
   * ------------------------------------------------------------- */
  const schemaListContainer = document.getElementById('laca_schema_list');
  const schemasJsonInput = document.getElementById('laca_seo_schemas_json');
  const newSchemaSelect = document.getElementById('laca_new_schema_select');
  const addSchemaBtn = document.getElementById('laca_add_schema_btn');
  const useDefaultBtn = document.getElementById('laca_use_default_schema_btn');
  const defaultNotice = document.getElementById('laca_schema_default_notice');

  const schemaDefinitions = window.lacaSeoEditorData && window.lacaSeoEditorData.schemaDefinitions ? window.lacaSeoEditorData.schemaDefinitions : {};
  const schemaTypes = window.lacaSeoEditorData && window.lacaSeoEditorData.schemaTypes ? window.lacaSeoEditorData.schemaTypes : {};

  let currentSchemas = [];
  if (schemasJsonInput && schemasJsonInput.value) {
    try {
      const parsed = JSON.parse(schemasJsonInput.value);
      if (Array.isArray(parsed)) {
        currentSchemas = parsed;
      }
    } catch {
      currentSchemas = [];
    }
  }

  function syncSchemasJson() {
    if (schemasJsonInput) {
      schemasJsonInput.value = JSON.stringify(currentSchemas);
    }
    if (defaultNotice) {
      defaultNotice.style.display = currentSchemas.length === 0 ? 'flex' : 'none';
    }
  }

  const schemaFieldTranslations = {
    headline: { label: 'Tiêu đề chính', tooltip: 'Tiêu đề hiển thị cho Schema' },
    description: { label: 'Mô tả tóm tắt', tooltip: 'Đoạn mô tả ngắn gọn về nội dung hoặc thực thể' },
    keywords: { label: 'Từ khóa', tooltip: 'Các từ khóa liên quan, phân cách bằng dấu phẩy' },
    '@type': { label: 'Loại bài viết (Article Type)', tooltip: 'Chỉ định loại cụ thể cho Article (Article, BlogPosting, NewsArticle)' },
    author: { label: 'Tác giả', tooltip: 'Thông tin tác giả biên soạn nội dung' },
    name: { label: 'Tên / Tiêu đề', tooltip: 'Tên gọi của thực thể hoặc sản phẩm' },
    datePublished: { label: 'Ngày xuất bản', tooltip: 'Thời gian xuất bản bài viết theo định dạng ISO' },
    dateModified: { label: 'Ngày cập nhật', tooltip: 'Thời gian cập nhật lần cuối theo định dạng ISO' },
    image: { label: 'Hình ảnh đại diện', tooltip: 'Thông tin hình ảnh đi kèm Schema' },
    url: { label: 'Đường dẫn (URL)', tooltip: 'Đường dẫn liên kết trực tiếp' },
    articleSection: { label: 'Chuyên mục bài viết', tooltip: 'Chuyên mục hoặc phân loại của bài' },
    reviewLocation: { label: 'Vị trí hiển thị đánh giá', tooltip: 'Vị trí chèn khối đánh giá trong nội dung' },
    reviewLocationShortcode: { label: 'Mã ngắn (Shortcode)', tooltip: 'Shortcode hiển thị đánh giá' },
    review: { label: 'Đánh giá (Review)', tooltip: 'Thông tin đánh giá hoặc nhận xét' },
    reviewRating: { label: 'Xếp hạng đánh giá', tooltip: 'Điểm số xếp hạng chi tiết' },
    ratingValue: { label: 'Điểm đánh giá', tooltip: 'Điểm đánh giá (vd: 5 hoặc 4.5)' },
    worstRating: { label: 'Điểm thấp nhất', tooltip: 'Mức điểm đánh giá tối thiểu (mặc định 1)' },
    bestRating: { label: 'Điểm cao nhất', tooltip: 'Mức điểm đánh giá tối đa (mặc định 5)' },
    hasPart: { label: 'Các phiên bản sách (Editions)', tooltip: 'Danh sách các phiên bản xuất bản của cuốn sách' },
    bookEdition: { label: 'Lần tái bản / Phiên bản', tooltip: 'Phiên bản xuất bản (vd: Tái bản lần 1)' },
    isbn: { label: 'Mã số ISBN', tooltip: 'Mã số tiêu chuẩn quốc tế cho sách' },
    bookFormat: { label: 'Định dạng sách', tooltip: 'Định dạng sách (Bìa cứng, bìa mềm, ebook...)' },
    provider: { label: 'Đơn vị cung cấp', tooltip: 'Tổ chức hoặc cá nhân cung cấp khóa học' },
    sameAs: { label: 'Liên kết mạng xã hội / Trang chủ', tooltip: 'URL tham chiếu chính thức của tổ chức hoặc cá nhân' },
    hasCourseInstance: { label: 'Lớp học / Kỳ học cụ thể', tooltip: 'Thông tin các khóa học mở thực tế' },
    courseMode: { label: 'Hình thức học', tooltip: 'Trực tiếp (onsite), trực tuyến (online) hoặc kết hợp' },
    courseWorkload: { label: 'Thời lượng học tập', tooltip: 'Khối lượng thời gian học (vd: 40 giờ)' },
    courseSchedule: { label: 'Lịch học', tooltip: 'Lịch học chi tiết định kỳ' },
    duration: { label: 'Thời lượng', tooltip: 'Khoảng thời gian (vd: PT30M cho 30 phút)' },
    repeatCount: { label: 'Số lần lặp lại', tooltip: 'Số lần khóa học được tổ chức lặp lại' },
    repeatFrequency: { label: 'Tần suất lặp lại', tooltip: 'Tần suất tổ chức khóa học' },
    startDate: { label: 'Thời gian bắt đầu', tooltip: 'Ngày và giờ bắt đầu sự kiện' },
    endDate: { label: 'Thời gian kết thúc', tooltip: 'Ngày và giờ kết thúc sự kiện' },
    offers: { label: 'Ưu đãi & Vé / Bán hàng', tooltip: 'Thông tin vé hoặc giá bán sản phẩm' },
    category: { label: 'Danh mục', tooltip: 'Danh mục phân loại' },
    price: { label: 'Giá tiền', tooltip: 'Mức giá (chỉ nhập số)' },
    priceCurrency: { label: 'Đơn vị tiền tệ', tooltip: 'Mã tiền tệ quốc tế ISO (vd: VND, USD)' },
    availability: { label: 'Tình trạng còn hàng / vé', tooltip: 'Tình trạng sẵn có (Còn hàng, Hết hàng, Đặt trước...)' },
    validFrom: { label: 'Ngày bắt đầu áp dụng giá', tooltip: 'Thời điểm bắt đầu có hiệu lực của mức giá' },
    priceValidUntil: { label: 'Ngày hết hạn giá', tooltip: 'Thời điểm hết hạn của mức giá ưu đãi' },
    inventoryLevel: { label: 'Số lượng trong kho', tooltip: 'Số lượng vé hoặc sản phẩm còn lại' },
    eventStatus: { label: 'Trạng thái sự kiện', tooltip: 'Sự kiện đã lên lịch, hoãn hay bị huỷ' },
    eventAttendanceMode: { label: 'Hình thức tham gia sự kiện', tooltip: 'Tham gia trực tiếp, trực tuyến hoặc hỗn hợp' },
    VirtualLocation: { label: 'Địa điểm trực tuyến', tooltip: 'Đường dẫn phòng họp hoặc luồng trực tuyến' },
    location: { label: 'Địa điểm tổ chức', tooltip: 'Địa điểm thực tế diễn ra sự kiện' },
    address: { label: 'Địa chỉ chi tiết', tooltip: 'Địa chỉ bưu chính đầy đủ' },
    streetAddress: { label: 'Địa chỉ đường / số nhà', tooltip: 'Tên đường, số nhà, ngõ ngách' },
    addressLocality: { label: 'Quận / Huyện / Thành phố', tooltip: 'Đơn vị hành chính cấp quận, huyện hoặc thành phố' },
    addressRegion: { label: 'Tỉnh / Thành phố trực thuộc', tooltip: 'Tỉnh hoặc thành phố' },
    postalCode: { label: 'Mã bưu điện (Postal Code)', tooltip: 'Mã bưu chính của khu vực' },
    addressCountry: { label: 'Quốc gia', tooltip: 'Mã quốc gia ISO (vd: VN, US)' },
    performer: { label: 'Người biểu diễn / Diễn giả', tooltip: 'Thông tin cá nhân biểu diễn hoặc diễn giả' },
    title: { label: 'Chức danh / Tiêu đề tuyển dụng', tooltip: 'Tên vị trí công việc' },
    baseSalary: { label: 'Mức lương cơ bản', tooltip: 'Thông tin mức lương của công việc' },
    currency: { label: 'Đơn vị tiền tệ lương', tooltip: 'Mã tiền tệ (VND, USD...)' },
    value: { label: 'Mức lương', tooltip: 'Khoản lương cụ thể hoặc dải lương' },
    unitText: { label: 'Kỳ trả lương', tooltip: 'Trả theo tháng (MONTH), năm (YEAR), ngày (DAY)...' },
    datePosted: { label: 'Ngày đăng tuyển', tooltip: 'Ngày tin tuyển dụng được đăng tải' },
    validThrough: { label: 'Hạn nộp hồ sơ', tooltip: 'Thời hạn cuối cùng nhận hồ sơ ứng tuyển' },
    unpublish: { label: 'Ẩn bài khi hết hạn', tooltip: 'Tự động gỡ tin khi quá hạn nộp' },
    employmentType: { label: 'Hình thức làm việc', tooltip: 'Toàn thời gian, bán thời gian, hợp đồng...' },
    hiringOrganization: { label: 'Nhà tuyển dụng / Công ty', tooltip: 'Thông tin doanh nghiệp tuyển dụng' },
    logo: { label: 'Logo tổ chức / doanh nghiệp', tooltip: 'Đường dẫn ảnh logo của công ty' },
    id: { label: 'Mã định danh tuyển dụng', tooltip: 'Mã công việc hoặc số hiệu tham chiếu' },
    jobLocation: { label: 'Nơi làm việc', tooltip: 'Địa điểm làm việc của ứng viên' },
    email: { label: 'Địa chỉ Email', tooltip: 'Email liên hệ' },
    gender: { label: 'Giới tính', tooltip: 'Giới tính của nhân vật / ứng viên' },
    jobTitle: { label: 'Chức danh nghề nghiệp', tooltip: 'Chức vụ hoặc nghề nghiệp chuyên môn' },
    sku: { label: 'Mã sản phẩm (SKU)', tooltip: 'Mã quản lý kho của sản phẩm' },
    brand: { label: 'Thương hiệu sản phẩm', tooltip: 'Tên thương hiệu hoặc nhà sản xuất' },
    gtin8: { label: 'Mã vạch GTIN / Barcode', tooltip: 'Mã sản phẩm toàn cầu GTIN-8, GTIN-12 hoặc GTIN-13' },
    mpn: { label: 'Mã phụ tùng (MPN)', tooltip: 'Mã định danh linh kiện của nhà sản xuất' },
    prepTime: { label: 'Thời gian chuẩn bị', tooltip: 'Thời gian chuẩn bị nguyên liệu (vd: PT15M)' },
    cookTime: { label: 'Thời gian nấu', tooltip: 'Thời gian nấu nướng (vd: PT30M)' },
    totalTime: { label: 'Tổng thời gian', tooltip: 'Tổng thời gian thực hiện món ăn' },
    recipeCategory: { label: 'Phân loại món ăn', tooltip: 'Bữa sáng, món chính, tráng miệng...' },
    recipeCuisine: { label: 'Ẩm thực vùng miền', tooltip: 'Ẩm thực Việt Nam, Ý, Pháp...' },
    recipeYield: { label: 'Khẩu phần phục vụ', tooltip: 'Số lượng người dùng (vd: 4 người)' },
    nutrition: { label: 'Giá trị dinh dưỡng', tooltip: 'Thông tin dinh dưỡng của món' },
    calories: { label: 'Lượng calo (Calories)', tooltip: 'Hàm lượng calo (vd: 250 calories)' },
    recipeIngredient: { label: 'Nguyên liệu món ăn', tooltip: 'Danh sách các nguyên liệu cần chuẩn bị' },
    video: { label: 'Video hướng dẫn', tooltip: 'Video clip hướng dẫn các bước' },
    embedUrl: { label: 'Đường dẫn nhúng (Embed URL)', tooltip: 'URL nhúng video player' },
    contentUrl: { label: 'Đường dẫn file video (Content URL)', tooltip: 'URL trực tiếp đến file video' },
    thumbnailUrl: { label: 'Ảnh thu nhỏ (Thumbnail)', tooltip: 'Ảnh đại diện của video' },
    uploadDate: { label: 'Ngày tải video lên', tooltip: 'Thời gian đăng tải video' },
    instructionType: { label: 'Kiểu hướng dẫn', tooltip: 'Hiển thị dạng văn bản đơn hoặc từng bước (HowToStep)' },
    instructionsSingleField: { label: 'Nội dung hướng dẫn', tooltip: 'Hướng dẫn tổng hợp trong một khối văn bản' },
    instructionsHowToStep: { label: 'Các bước thực hiện (How-To Steps)', tooltip: 'Danh sách các bước thực hiện tuần tự' },
    text: { label: 'Văn bản hướng dẫn', tooltip: 'Mô tả chi tiết nội dung của bước' },
    telephone: { label: 'Số điện thoại', tooltip: 'Số điện thoại liên hệ đặt bàn hoặc hỗ trợ' },
    priceRange: { label: 'Mức giá trung bình', tooltip: 'Biểu thị khoảng giá (vd: $$, $$$ hoặc 100k - 500k)' },
    geo: { label: 'Tọa độ địa lý (Geo)', tooltip: 'Tọa độ kinh độ và vĩ độ' },
    latitude: { label: 'Vĩ độ (Latitude)', tooltip: 'Tọa độ vĩ độ địa lý' },
    longitude: { label: 'Kinh độ (Longitude)', tooltip: 'Tọa độ kinh độ địa lý' },
    openingHoursSpecification: { label: 'Khung giờ hoạt động', tooltip: 'Thời gian mở cửa và đóng cửa theo ngày' },
    dayOfWeek: { label: 'Các ngày trong tuần', tooltip: 'Ngày áp dụng khung giờ' },
    opens: { label: 'Giờ mở cửa', tooltip: 'Thời gian mở cửa (vd: 08:00)' },
    closes: { label: 'Giờ đóng cửa', tooltip: 'Thời gian đóng cửa (vd: 22:00)' },
    servesCuisine: { label: 'Loại hình món ăn phục vụ', tooltip: 'Các món ẩm thực nhà hàng phục vụ' },
    hasMenu: { label: 'Đường dẫn thực đơn (Menu URL)', tooltip: 'Liên kết tới trang thực đơn chi tiết' },
    serviceType: { label: 'Loại hình dịch vụ', tooltip: 'Tên gọi gói dịch vụ cung cấp' },
    operatingSystem: { label: 'Hệ điều hành tương thích', tooltip: 'Hệ điều hành hỗ trợ (Windows, macOS, iOS, Android)' },
    applicationCategory: { label: 'Danh mục ứng dụng', tooltip: 'Phân loại ứng dụng phần mềm' },
  };

  function getFieldInfo(fieldDef) {
    const key = fieldDef.key;
    const trans = schemaFieldTranslations[key];
    const label = (trans && trans.label) ? trans.label : (fieldDef.label || key);
    const tooltip = (trans && trans.tooltip) ? trans.tooltip : (fieldDef.help || '');
    return { label, tooltip };
  }

  function getFieldDefs(type) {
    if (type === 'BlogPosting' || type === 'NewsArticle') {
      return schemaDefinitions.Article || [];
    }
    return schemaDefinitions[type] || [];
  }

  function renderSchemaList() {
    if (!schemaListContainer) return;
    schemaListContainer.replaceChildren();

    currentSchemas.forEach((schema, sIdx) => {
      const type = schema['@type'] || 'Article';
      const metadata = schema.metadata || {};
      const isPrimary = !!metadata.isPrimary;
      const title = metadata.title || type;

      const itemEl = document.createElement('div');
      itemEl.className = `laca-schema-item ${isPrimary ? 'is-primary' : ''}`;
      itemEl.dataset.index = sIdx;
      itemEl.dataset.type = type;

      // Header
      const headerEl = document.createElement('div');
      headerEl.className = 'laca-schema-item__header';

      const titleWrap = document.createElement('div');
      titleWrap.className = 'laca-schema-item__title';
      titleWrap.innerHTML = `
        <span class="dashicons dashicons-arrow-right-alt2"></span>
        <strong>${escapeHtml(title)}</strong>
        <span class="laca-badge-type">${escapeHtml(type)}</span>
        ${isPrimary ? '<span class="laca-badge-primary">Chính (Primary)</span>' : ''}
      `;

      const actionsWrap = document.createElement('div');
      actionsWrap.className = 'laca-schema-item__actions';

      if (!isPrimary) {
        const makePrimaryBtn = document.createElement('button');
        makePrimaryBtn.type = 'button';
        makePrimaryBtn.className = 'button button-small laca-make-primary-btn';
        makePrimaryBtn.innerHTML = '<span class="dashicons dashicons-star-filled"></span> Đặt làm chính';
        makePrimaryBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          currentSchemas.forEach((s, i) => {
            if (!s.metadata) s.metadata = {};
            s.metadata.isPrimary = (i === sIdx);
          });
          syncSchemasJson();
          renderSchemaList();
        });
        actionsWrap.appendChild(makePrimaryBtn);
      }

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'button button-small button-link-delete laca-remove-schema-btn';
      removeBtn.innerHTML = '<span class="dashicons dashicons-trash"></span> Xoá';
      removeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        currentSchemas.splice(sIdx, 1);
        if (currentSchemas.length > 0 && !currentSchemas.some(s => s.metadata && s.metadata.isPrimary)) {
          if (!currentSchemas[0].metadata) currentSchemas[0].metadata = {};
          currentSchemas[0].metadata.isPrimary = true;
        }
        syncSchemasJson();
        renderSchemaList();
      });
      actionsWrap.appendChild(removeBtn);

      headerEl.appendChild(titleWrap);
      headerEl.appendChild(actionsWrap);

      // Body Form
      const bodyEl = document.createElement('div');
      bodyEl.className = 'laca-schema-item__body';

      const fieldDefs = getFieldDefs(type);
      fieldDefs.forEach(fieldDef => {
        const fEl = renderField(fieldDef, schema, (newVal) => {
          setSchemaValue(schema, fieldDef, newVal);
          syncSchemasJson();
        }, false);
        if (fEl) bodyEl.appendChild(fEl);
      });

      // Toggle accordion
      headerEl.addEventListener('click', () => {
        itemEl.classList.toggle('is-open');
      });

      // Open first item or primary item by default
      if (sIdx === 0 || isPrimary) {
        itemEl.classList.add('is-open');
      }

      itemEl.appendChild(headerEl);
      itemEl.appendChild(bodyEl);
      schemaListContainer.appendChild(itemEl);
    });
  }

  function setSchemaValue(targetObj, fieldDef, value) {
    const key = fieldDef.key;
    if (fieldDef.save === 'metadata') {
      if (!targetObj.metadata) targetObj.metadata = {};
      targetObj.metadata[key] = value;
    } else {
      targetObj[key] = value;
    }
  }

  function getSchemaValue(targetObj, fieldDef) {
    const key = fieldDef.key;
    if (fieldDef.save === 'metadata') {
      return (targetObj.metadata && targetObj.metadata[key] !== undefined) ? targetObj.metadata[key] : (fieldDef.default || '');
    }
    return targetObj[key] !== undefined ? targetObj[key] : (fieldDef.default || '');
  }

  function renderField(fieldDef, parentObj, onChange, isSubObject = false) {
    if (!fieldDef || !fieldDef.key) return null;

    // Check if sub-object @type field: hide and fix constant value like Rank Math
    if (isSubObject && fieldDef.key === '@type') {
      const fixedTypeVal = fieldDef.value || fieldDef.default || '';
      if (fixedTypeVal && parentObj['@type'] !== fixedTypeVal) {
        parentObj['@type'] = fixedTypeVal;
        onChange(fixedTypeVal);
      }
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.dataset.fieldName = '@type';
      hiddenInput.value = fixedTypeVal;
      return hiddenInput;
    }

    // Check if repeater (isArray && subfields)
    if (fieldDef.isArray && (fieldDef.subfields || fieldDef.fields)) {
      return renderRepeaterField(fieldDef, parentObj, onChange);
    }

    // Check if group (isGroup && subfields)
    if (fieldDef.isGroup && (fieldDef.subfields || fieldDef.fields)) {
      return renderGroupField(fieldDef, parentObj, onChange);
    }

    const fieldWrap = document.createElement('div');
    fieldWrap.className = 'laca-schema-field';

    const info = getFieldInfo(fieldDef);

    const labelEl = document.createElement('label');
    labelEl.textContent = info.label;
    if (fieldDef.required) {
      const req = document.createElement('span');
      req.className = 'required';
      req.textContent = '*';
      labelEl.appendChild(req);
    }
    if (info.tooltip) {
      const tipIcon = document.createElement('span');
      tipIcon.className = 'dashicons dashicons-editor-help laca-field-tooltip-icon';
      tipIcon.title = info.tooltip;
      tipIcon.style.fontSize = '15px';
      tipIcon.style.width = '15px';
      tipIcon.style.height = '15px';
      tipIcon.style.marginLeft = '4px';
      tipIcon.style.color = '#8c8f94';
      tipIcon.style.cursor = 'help';
      tipIcon.style.verticalAlign = 'middle';
      labelEl.appendChild(tipIcon);
    }
    fieldWrap.appendChild(labelEl);

    const curVal = getSchemaValue(parentObj, fieldDef);

    if (fieldDef.type === 'select') {
      const select = document.createElement('select');
      select.dataset.fieldName = fieldDef.key;
      const opts = fieldDef.options || {};
      Object.keys(opts).forEach(optKey => {
        const opt = document.createElement('option');
        opt.value = optKey;
        opt.textContent = opts[optKey];
        if (optKey === curVal) opt.selected = true;
        select.appendChild(opt);
      });
      select.addEventListener('change', () => onChange(select.value));
      fieldWrap.appendChild(select);
    } else if (fieldDef.type === 'radio') {
      const radioWrap = document.createElement('div');
      radioWrap.className = 'laca-radio-group';
      radioWrap.dataset.fieldName = fieldDef.key;
      const opts = fieldDef.options || {};
      const radName = 'rad_' + Math.random().toString(36).substring(2, 9);
      Object.keys(opts).forEach(optKey => {
        const rLabel = document.createElement('label');
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = radName;
        radio.value = optKey;
        radio.dataset.fieldName = fieldDef.key;
        if (optKey === curVal || (!curVal && optKey === fieldDef.default)) radio.checked = true;
        radio.addEventListener('change', () => onChange(radio.value));
        rLabel.appendChild(radio);
        rLabel.appendChild(document.createTextNode(' ' + opts[optKey]));
        radioWrap.appendChild(rLabel);
      });
      fieldWrap.appendChild(radioWrap);
    } else if (fieldDef.type === 'textarea') {
      const textarea = document.createElement('textarea');
      textarea.dataset.fieldName = fieldDef.key;
      textarea.rows = 3;
      textarea.placeholder = fieldDef.placeholder || '';
      textarea.value = curVal;
      textarea.addEventListener('input', () => onChange(textarea.value));
      fieldWrap.appendChild(textarea);
    } else if (fieldDef.type === 'datetimepicker') {
      const input = document.createElement('input');
      input.dataset.fieldName = fieldDef.key;
      input.type = 'datetime-local';
      input.value = curVal ? curVal.replace(' ', 'T').substring(0, 16) : '';
      input.addEventListener('input', () => onChange(input.value));
      fieldWrap.appendChild(input);
    } else if (fieldDef.type === 'datepicker') {
      const input = document.createElement('input');
      input.dataset.fieldName = fieldDef.key;
      input.type = 'date';
      input.value = curVal ? curVal.substring(0, 10) : '';
      input.addEventListener('input', () => onChange(input.value));
      fieldWrap.appendChild(input);
    } else {
      const input = document.createElement('input');
      input.dataset.fieldName = fieldDef.key;
      input.type = fieldDef.type === 'number' ? 'number' : 'text';
      input.placeholder = fieldDef.placeholder || '';
      input.value = curVal;
      input.addEventListener('input', () => onChange(input.value));
      fieldWrap.appendChild(input);
    }

    if (info.tooltip) {
      const help = document.createElement('p');
      help.className = 'description';
      help.textContent = info.tooltip;
      fieldWrap.appendChild(help);
    }

    return fieldWrap;
  }

  function renderGroupField(groupDef, parentObj, onChange) {
    const groupWrap = document.createElement('div');
    groupWrap.className = 'laca-schema-subgroup';

    const info = getFieldInfo(groupDef);

    const groupTitle = document.createElement('h5');
    groupTitle.className = 'laca-schema-subgroup__title';
    groupTitle.textContent = info.label;
    if (info.tooltip) {
      const tipIcon = document.createElement('span');
      tipIcon.className = 'dashicons dashicons-editor-help laca-field-tooltip-icon';
      tipIcon.title = info.tooltip;
      tipIcon.style.fontSize = '14px';
      tipIcon.style.width = '14px';
      tipIcon.style.height = '14px';
      tipIcon.style.marginLeft = '4px';
      tipIcon.style.color = '#8c8f94';
      tipIcon.style.cursor = 'help';
      tipIcon.style.verticalAlign = 'middle';
      groupTitle.appendChild(tipIcon);
    }
    groupWrap.appendChild(groupTitle);

    const subfields = groupDef.subfields || groupDef.fields || [];
    let groupVal = parentObj[groupDef.key];
    if (!groupVal || typeof groupVal !== 'object' || Array.isArray(groupVal)) {
      groupVal = {};
      parentObj[groupDef.key] = groupVal;
    }

    subfields.forEach(subDef => {
      const sfEl = renderField(subDef, groupVal, (newVal) => {
        setSchemaValue(groupVal, subDef, newVal);
        onChange(groupVal);
      }, true);
      if (sfEl) groupWrap.appendChild(sfEl);
    });

    return groupWrap;
  }

  function renderRepeaterField(repeaterDef, parentObj, onChange) {
    const repWrap = document.createElement('div');
    repWrap.className = 'laca-schema-repeater';

    const info = getFieldInfo(repeaterDef);

    const header = document.createElement('div');
    header.className = 'laca-schema-repeater__header';
    header.innerHTML = `<strong>${escapeHtml(info.label)}</strong>`;
    if (info.tooltip) {
      const tip = document.createElement('span');
      tip.className = 'dashicons dashicons-editor-help laca-field-tooltip-icon';
      tip.title = info.tooltip;
      tip.style.fontSize = '14px';
      tip.style.width = '14px';
      tip.style.height = '14px';
      tip.style.marginLeft = '4px';
      tip.style.color = '#8c8f94';
      tip.style.cursor = 'help';
      tip.style.verticalAlign = 'middle';
      header.querySelector('strong').appendChild(tip);
    }

    const addRowBtn = document.createElement('button');
    addRowBtn.type = 'button';
    addRowBtn.className = 'button button-small';
    addRowBtn.innerHTML = '<span class="dashicons dashicons-plus"></span> Thêm';
    header.appendChild(addRowBtn);
    repWrap.appendChild(header);

    const listEl = document.createElement('div');
    listEl.className = 'laca-schema-repeater__list';
    repWrap.appendChild(listEl);

    let rows = parentObj[repeaterDef.key];
    if (!Array.isArray(rows)) {
      rows = [];
      parentObj[repeaterDef.key] = rows;
    }

    const subdefs = repeaterDef.subfields || repeaterDef.fields || [];

    function renderRows() {
      listEl.replaceChildren();
      rows.forEach((rowObj, rIdx) => {
        const rowEl = document.createElement('div');
        rowEl.className = 'laca-schema-repeater__row';

        const rowHeader = document.createElement('div');
        rowHeader.className = 'laca-schema-repeater__row-header';
        rowHeader.innerHTML = `<span>#${rIdx + 1}</span>`;

        const delRowBtn = document.createElement('button');
        delRowBtn.type = 'button';
        delRowBtn.className = 'button button-small button-link-delete';
        delRowBtn.innerHTML = '<span class="dashicons dashicons-trash"></span>';
        delRowBtn.addEventListener('click', () => {
          rows.splice(rIdx, 1);
          renderRows();
          onChange(rows);
        });
        rowHeader.appendChild(delRowBtn);
        rowEl.appendChild(rowHeader);

        subdefs.forEach(subDef => {
          const sfEl = renderField(subDef, rowObj, (newVal) => {
            setSchemaValue(rowObj, subDef, newVal);
            onChange(rows);
          });
          if (sfEl) rowEl.appendChild(sfEl);
        });

        listEl.appendChild(rowEl);
      });
    }

    addRowBtn.addEventListener('click', () => {
      const newRow = {};
      subdefs.forEach(subDef => {
        if (subDef.default) {
          newRow[subDef.key] = subDef.default;
        }
      });
      rows.push(newRow);
      renderRows();
      onChange(rows);
    });

    renderRows();
    return repWrap;
  }

  // Handle Add Schema
  if (addSchemaBtn && newSchemaSelect) {
    addSchemaBtn.addEventListener('click', () => {
      const selectedType = newSchemaSelect.value;
      if (!selectedType) return;

      const isFirst = currentSchemas.length === 0;
      const typeLabel = (schemaTypes && schemaTypes[selectedType]) ? schemaTypes[selectedType] : selectedType;

      const newSchemaObj = {
        '@type': selectedType,
        metadata: {
          title: selectedType,
          type: 'template',
          shortcode: 's-' + Math.random().toString(36).substring(2, 9),
          isPrimary: isFirst,
        }
      };

      // Fill in defaults
      const defs = getFieldDefs(selectedType);
      defs.forEach(fDef => {
        if (fDef.default) {
          setSchemaValue(newSchemaObj, fDef, fDef.default);
        } else if (fDef.placeholder && fDef.placeholder.startsWith('%')) {
          setSchemaValue(newSchemaObj, fDef, fDef.placeholder);
        }
      });

      currentSchemas.push(newSchemaObj);
      newSchemaSelect.value = '';
      syncSchemasJson();
      renderSchemaList();
    });
  }

  // Handle Use Default Schema
  if (useDefaultBtn) {
    useDefaultBtn.addEventListener('click', () => {
      if (confirm('Bạn có chắc muốn dùng Schema mặc định theo loại bài? Tất cả Schema tuỳ biến của bài này sẽ bị xoá.')) {
        currentSchemas = [];
        syncSchemasJson();
        renderSchemaList();
      }
    });
  }

  // Initial render of Schema List
  renderSchemaList();

  // Initial full cycle
  runFullCycle();
});
