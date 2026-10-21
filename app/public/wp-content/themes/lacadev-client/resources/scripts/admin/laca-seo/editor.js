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
      renderGroupTests('basic', ['keywordInTitle', 'keywordInMetaDescription', 'keywordInPermalink', 'keywordIn10Percent', 'keywordInContent', 'lengthContent'], analysis.results);
      renderGroupTests('additional', ['keywordInSubheadings', 'keywordInImageAlt', 'keywordDensity', 'lengthPermalink', 'linksHasExternals', 'linksNotAllExternals', 'linksHasInternal', 'keywordNotUsed'], analysis.results);
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

  // Initial full cycle
  runFullCycle();
});
