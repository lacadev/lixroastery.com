/**
 * Content Analyzer for Laca SEO.
 * Ported 1:1 from Rank Math analyzer.js (ResultManager & scoring algorithms).
 * Exposes window.lacaSeoAnalysis = { score, results: { [testId]: { ok: bool, score: number, maxScore: number } } }
 */

import sentimentData from './data/sentiment.json';

// --- Text Processing Helpers ported 1:1 from Rank Math analyzer.js ---

// RM: d(text) -> stripTags
export const stripTags = (e) => (e || '').replace(/<\/?[a-z][^>]*?>/gi, '');

// RM: D(text) -> stripComments
export const stripComments = (e) => (e || '').replace(/<!--[\s\S]*?-->/g, '');

// RM: g(text) -> stripShortcodes
const fShortcode = '[^<>&/\\[\\]\0- =]+?';
const yShortcode = new RegExp('\\[' + fShortcode + '( [^\\]]+?)?\\]', 'g');
const hShortcode = new RegExp('\\[/' + fShortcode + '\\]', 'g');
export const stripShortcodes = (e) => (e || '').replace(yShortcode, '').replace(hShortcode, '');

// RM: p(text) -> normalizeWhitespace
export const normalizeWhitespace = (e) =>
  (e || '')
    .replace(/&nbsp;|&#160;/gi, ' ')
    .replace(/\s{2,}/g, ' ')
    .replace(/\s\./g, '.')
    .replace(/^\s+|\s+$/g, '');

// RM: w(text) -> stripEntities
export const stripEntities = (e) => (e || '').replace(/&\S+?;/g, '');

// RM: m(text) -> replaceDashes
export const replaceDashes = (e) => (e || '').replace(/--|\u2014/g, ' ');

// RM: v(text) -> stripSymbols
const bSymbols = new RegExp(['[', '\u0080-\u00bf\u00d7\u00f7', '\u2000-\u2bff', '\u2e00-\u2e7f', ']'].join(''), 'g');
export const stripSymbols = (e) => (e || '').replace(bSymbols, '');

// RM: A(text) -> trimPunctuation
const kPunc = '[\\–\\-\\(\\)_\\[\\]’“”"\'`.?!:;,¿¡«»‹›—×+&<>]+';
const FPunc = new RegExp('^' + kPunc);
const EPunc = new RegExp(kPunc + '$');
export const trimPunctuation = (e) => (e || '').replace(FPunc, '').replace(EPunc, '');

// RM: $e(text) -> normalizeQuotes
export const normalizeQuotes = (e) => (e || '').replace(/[‘’‛`]/g, "'").replace(/[“”〝〞〟‟„]/g, '"');

// RM: St(text) -> escapeRegex
export const escapeRegex = (e) => (e || '').replace(/[\\^$*+?.()|[\]{}]/g, '\\$&');

// RM: C(text) -> getWords
export const getWords = (text, limit = false) => {
  if (!text) return [];
  let t = stripTags(text);
  t = stripComments(t);
  t = stripShortcodes(t);
  t = normalizeWhitespace(t);
  t = stripEntities(t);
  t = replaceDashes(t);
  t = stripSymbols(t);
  if (t === '') return [];
  const words = t.split(/\s/g).map(trimPunctuation).filter(w => w.trim() !== '');
  if (words.length === 0) return [];
  return limit === false ? words : words.slice(0, limit);
};

// RM: Ke(text) -> cleanTextForContent (d, p)
export const cleanTextForContent = (text) => {
  if (typeof text === 'undefined' || text === null) return '';
  return normalizeWhitespace(stripTags(text));
};

// RM: Ht(keyword, text) -> keywordInText
export const Ht = (kw, text) => {
  const tWords = getWords(text);
  if (!tWords || tWords.length === 0) return false;
  const kwWords = getWords(normalizeQuotes((kw || '').toLowerCase()));
  if (!kwWords || kwWords.length === 0) return false;
  return tWords.join(' ').toLowerCase().includes(kwWords.join(' '));
};

// RM: wordCount(text) -> word count
export const wordCount = (text) => {
  if (typeof window !== 'undefined' && window.wp && window.wp.wordcount && window.wp.wordcount.count) {
    return window.wp.wordcount.count(text || '', 'words');
  }
  return getWords(text).length;
};

// RM: cleanForSlug
export const cleanForSlug = (text) => {
  if (typeof window !== 'undefined' && window.wp && window.wp.url && window.wp.url.cleanForSlug) {
    return window.wp.url.cleanForSlug(text);
  }
  return (text || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
};

// RM: getParagraphs (He + Ve)
export const getParagraphs = (content) => {
  if (!content) return [];
  let autopText = content;
  if (typeof window !== 'undefined' && window.wp && window.wp.autop && window.wp.autop.autop) {
    autopText = window.wp.autop.autop(content);
  }
  const pRegex = /<p(?:[^>]+)?>(.*?)<\/p>/gi;
  const matches = [];
  let m;
  while ((m = pRegex.exec(autopText)) !== null) {
    matches.push(m[1]);
  }
  const rawList = matches.length > 0 ? matches : [autopText];
  const list = [];
  rawList.forEach(p => {
    const wc = wordCount(p);
    if (wc > 0) {
      list.push({ wordCount: wc, text: p });
    }
  });
  return list;
};

// RM: getLinkStats (K, W, U)
export const getLinkStats = (content, domain = '') => {
  const anchors = (content || '').match(/<a [^>]*href=(["'])[a-z/]([^"']+)[^>]*>/gi) || [];
  const stats = {
    total: anchors.length,
    internalTotal: 0,
    internalDofollow: 0,
    internalNofollow: 0,
    externalTotal: 0,
    externalDofollow: 0,
    externalNofollow: 0,
    otherTotal: 0,
    otherDofollow: 0,
    otherNofollow: 0,
    anchors,
  };

  const parentDomain = domain || (typeof window !== 'undefined' && window.location ? window.location.hostname : 'lixroastery.local');

  anchors.forEach(aTag => {
    const hrefMatch = /href=(["'])([^"']+)\1/i.exec(aTag);
    const href = hrefMatch ? hrefMatch[2] : '';
    let linkType = 'external';

    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || (href.includes(':') && !href.startsWith('http:') && !href.startsWith('https:'))) {
      linkType = 'other';
    } else if (href.startsWith('/') && !href.startsWith('//')) {
      linkType = 'internal';
    } else {
      try {
        const parsed = new URL(href, 'http://' + parentDomain);
        if (!parsed.host || parsed.host === parentDomain || parsed.host.endsWith('.' + parentDomain)) {
          linkType = 'internal';
        } else {
          linkType = 'external';
        }
      } catch (e) {
        linkType = 'other';
      }
    }

    const lowerTag = aTag.toLowerCase();
    const isNofollow = lowerTag.includes('rel=') && lowerTag.includes('nofollow');
    const followType = isNofollow ? 'Nofollow' : 'Dofollow';

    stats[linkType + 'Total']++;
    stats[linkType + followType]++;
  });

  return stats;
};

// RM: titleSentiment (AFINN-165 + extras)
export const calculateSentiment = (titleWords) => {
  if (!sentimentData || !sentimentData.labels) return 0;
  const dict = Object.assign({}, sentimentData.labels, sentimentData.extras || {});
  const text = titleWords.join(' ');
  const tokens = text.toLowerCase()
    .replace(/\n/g, ' ')
    .replace(/[.,\/#!?$%\^&\*;:{}=_`"~()]/g, ' ')
    .replace(/\s\s+/g, ' ')
    .trim()
    .split(' ');

  let score = 0;
  for (let f = tokens.length; f--;) {
    const token = tokens[f];
    if (Object.prototype.hasOwnProperty.call(dict, token)) {
      let val = dict[token];
      if (f > 0 && sentimentData.negators && sentimentData.negators[tokens[f - 1]]) {
        val = -val;
      }
      score += val;
    }
  }
  return score;
};

/**
 * Analyzer Engine Class
 */
export class LacaSeoAnalyzer {
  constructor(options = {}) {
    this.options = Object.assign({
      isPost: true,
      isPage: false,
      isProduct: false,
      isTerm: false,
      isUser: false,
      isHome: false,
      isPostsPage: false,
      locale: 'en',
      powerWords: [],
      hasProductSchema: false,
      isReviewEnabled: false,
      parentDomain: '',
    }, options);
  }

  /**
   * Run full suite of tests against data.
   */
  analyze(data) {
    const title = (data.title || '').trim();
    const description = (data.description || '').trim();
    const keyword = (data.keyword || '').trim();
    const content = data.content || '';
    const permalink = data.permalink || '';
    const isKeywordNew = data.isKeywordNew !== false;
    const hasTOC = Boolean(data.hasTOC);
    const thumbnail = data.thumbnail || '';
    const thumbnailAlt = (data.thumbnailAlt || '').trim();
    const images = Array.isArray(data.images) ? data.images : [];
    const videos = Array.isArray(data.videos) ? data.videos : [];

    const shortLocale = (this.options.locale || 'en').split('_')[0].toLowerCase();
    const isEn = shortLocale === 'en';

    const results = {};
    let totalAchieved = 0;
    let totalMax = 0;

    const record = (id, ok, score, maxScore, message, tooltip) => {
      results[id] = { ok: Boolean(ok), score, maxScore, message, tooltip };
      totalAchieved += score;
      totalMax += maxScore;
    };

    const hasKw = keyword.length > 0;
    const titleLower = title.toLowerCase();
    const descLower = description.toLowerCase();
    const contentLower = content.toLowerCase();
    const titleWords = getWords(titleLower);

    // =========================================================================
    // 1. Term analysis: ONLY 5 tests run per RM Taxonomy_Screen::get_analysis()
    // =========================================================================
    if (this.options.isTerm) {
      // 1. keywordInTitle (max 36 for en, 38 for other)
      const kwInTitle = hasKw && Ht(keyword, titleLower);
      const maxKwInTitle = isEn ? 36 : 38;
      record(
        'keywordInTitle',
        kwInTitle,
        kwInTitle ? maxKwInTitle : 0,
        maxKwInTitle,
        kwInTitle ? "Hurray! You're using Focus Keyword in the SEO Title." : 'Add Focus Keyword to the SEO title.',
        'Make sure the focus keyword appears in the SEO post title too.'
      );

      // 2. keywordInMetaDescription (max 2)
      const kwInDesc = hasKw && Ht(keyword, descLower);
      record(
        'keywordInMetaDescription',
        kwInDesc,
        kwInDesc ? 2 : 0,
        2,
        kwInDesc ? 'Focus Keyword used inside SEO Meta Description.' : 'Add Focus Keyword to your SEO Meta Description.',
        'Make sure the focus keyword appears in the SEO description too.'
      );

      // 3. keywordInPermalink (max 5)
      let kwInPermalink = false;
      if (hasKw && permalink) {
        const cleanSlugStr = cleanForSlug(permalink.replace(/[-_]/gi, '-'));
        const cleanKwSlug = cleanForSlug(keyword.replace(/'/g, '').replace(/[-_.]+/g, '-'));
        kwInPermalink = cleanSlugStr.includes(cleanKwSlug);
      }
      record(
        'keywordInPermalink',
        kwInPermalink,
        kwInPermalink ? 5 : 0,
        5,
        kwInPermalink ? 'Focus Keyword used in the URL.' : 'Use Focus Keyword in the URL.',
        'Include the focus keyword in the slug (permalink) of this post.'
      );

      // 4. keywordNotUsed (max 0)
      record(
        'keywordNotUsed',
        isKeywordNew,
        0,
        0,
        isKeywordNew ? "You haven't used this Focus Keyword before." : 'You have already used this Focus Keyword.',
        ''
      );

      // 5. titleStartWithKeyword (max 3)
      let titleStart = false;
      if (hasKw && titleWords.length > 0) {
        const kwWords = getWords(normalizeQuotes(keyword.toLowerCase()));
        const kwStr = kwWords.join(' ');
        const tStr = titleWords.join(' ');
        const idx = tStr.indexOf(kwStr);
        const halfLen = Math.floor(tStr.length / 2);
        titleStart = idx >= 0 && idx < halfLen;
      }
      record(
        'titleStartWithKeyword',
        titleStart,
        titleStart ? 3 : 0,
        3,
        titleStart ? 'Focus Keyword used at the beginning of SEO title.' : 'Use the Focus Keyword near the beginning of SEO title.',
        'The SEO page title should contain the Focus Keyword preferably at the beginning.'
      );

      return this.finalize(totalAchieved, totalMax, results);
    }

    // =========================================================================
    // 2. Post / Product Analysis
    // Exclude tests according to RM Pro class-common.php and Post_Screen::exclude_tests()
    // =========================================================================
    const isProduct = Boolean(this.options.isProduct);
    const isHome = Boolean(this.options.isHome);
    const isPostsPage = Boolean(this.options.isPostsPage);

    // Tests excluded for Product in RM Pro:
    // keywordInSubheadings, linksHasExternals, linksNotAllExternals, linksHasInternal, titleSentiment, titleHasNumber, contentHasTOC
    const excludeProduct = isProduct;

    // keywordInTitle (max 36 for en, 38 for other)
    const kwInTitle = hasKw && Ht(keyword, titleLower);
    const maxKwInTitle = isEn ? 36 : 38;
    record(
      'keywordInTitle',
      kwInTitle,
      kwInTitle ? maxKwInTitle : 0,
      maxKwInTitle,
      kwInTitle ? 'Tuyệt vời! Bạn đang sử dụng Từ khoá chính trong Tiêu đề SEO.' : 'Thêm Từ khoá chính vào Tiêu đề SEO.',
      'Hãy chắc chắn rằng từ khoá chính xuất hiện trong tiêu đề SEO của bài viết.'
    );

    // keywordInMetaDescription (max 2)
    const kwInDesc = hasKw && Ht(keyword, descLower);
    record(
      'keywordInMetaDescription',
      kwInDesc,
      kwInDesc ? 2 : 0,
      2,
      kwInDesc ? 'Từ khoá chính đã được dùng trong Mô tả SEO.' : 'Thêm Từ khoá chính vào Mô tả SEO của bạn.',
      'Hãy đảm bảo từ khoá chính xuất hiện trong phần mô tả SEO.'
    );

    // keywordInPermalink (max 5) - excluded on isHome
    if (!isHome) {
      let kwInPermalink = false;
      if (hasKw && permalink) {
        const cleanSlugStr = cleanForSlug(permalink.replace(/[-_]/gi, '-'));
        const cleanKwSlug = cleanForSlug(keyword.replace(/'/g, '').replace(/[-_.]+/g, '-'));
        kwInPermalink = cleanSlugStr.includes(cleanKwSlug);
      }
      record(
        'keywordInPermalink',
        kwInPermalink,
        kwInPermalink ? 5 : 0,
        5,
        kwInPermalink ? 'Từ khoá chính xuất hiện trong đường dẫn URL.' : 'Sử dụng Từ khoá chính trong đường dẫn URL.',
        'Đưa từ khoá chính vào chuỗi đường dẫn tĩnh (slug/permalink) của bài này.'
      );
    }

    // keywordIn10Percent (max 3) - excluded on isPostsPage
    if (!isPostsPage) {
      let kwIn10Pct = false;
      if (hasKw && content) {
        let cWords = getWords(contentLower);
        if (cWords.length > 400) {
          cWords = cWords.slice(0, Math.floor(0.1 * cWords.length));
        }
        const first10Str = cWords.join(' ');
        const kwStr = getWords(normalizeQuotes(keyword.toLowerCase())).join(' ');
        kwIn10Pct = first10Str.includes(kwStr);
      }
      record(
        'keywordIn10Percent',
        kwIn10Pct,
        kwIn10Pct ? 3 : 0,
        3,
        kwIn10Pct ? 'Từ khoá chính xuất hiện trong 10% nội dung đầu tiên.' : 'Sử dụng Từ khoá chính ở phần đầu nội dung của bạn.',
        '10% đầu tiên của nội dung nên chứa Từ khoá chính, tốt nhất là ở những câu mở đầu.'
      );
    }

    // keywordInContent (max 3) - excluded on isPostsPage
    if (!isPostsPage) {
      const kwInContent = hasKw && Ht(keyword, cleanTextForContent(contentLower));
      record(
        'keywordInContent',
        kwInContent,
        kwInContent ? 3 : 0,
        3,
        kwInContent ? 'Tìm thấy Từ khoá chính trong nội dung.' : 'Sử dụng Từ khoá chính trong nội dung.',
        'Nên để từ khoá chính xuất hiện rải rác tự nhiên trong bài viết.'
      );
    }

    // lengthContent (max 8) - excluded on isPostsPage
    if (!isPostsPage) {
      const wc = wordCount(contentLower);
      let lengthScore = 0;
      let lengthOk = false;

      if (isProduct) {
        // Boundaries in product-analysis.js: recommended 200 (8), below 150 (5), med 120 (4), belowMed 100 (3), low 80 (2)
        if (wc >= 200) {
          lengthScore = 8;
          lengthOk = true;
        } else if (wc >= 150) {
          lengthScore = 5;
          lengthOk = true;
        } else if (wc >= 120) {
          lengthScore = 4;
          lengthOk = true;
        } else if (wc >= 100) {
          lengthScore = 3;
          lengthOk = true;
        } else if (wc >= 80) {
          lengthScore = 2;
          lengthOk = true;
        }
      } else {
        // Standard post boundaries: recommended 2500 (8), 2000 (5), 1500 (4), 1000 (3), 600 (2)
        if (wc >= 2500) {
          lengthScore = 8;
          lengthOk = true;
        } else if (wc >= 2000) {
          lengthScore = 5;
          lengthOk = true;
        } else if (wc >= 1500) {
          lengthScore = 4;
          lengthOk = true;
        } else if (wc >= 1000) {
          lengthScore = 3;
          lengthOk = true;
        } else if (wc >= 600) {
          lengthScore = 2;
          lengthOk = true;
        }
      }

      record(
        'lengthContent',
        lengthOk,
        lengthScore,
        8,
        lengthOk ? `Nội dung dài ${wc} từ. Làm tốt lắm!` : `Nội dung dài ${wc} từ. Hãy cân nhắc viết ít nhất ${isProduct ? 200 : 600} từ.`,
        `Độ dài nội dung tối thiểu khuyến nghị là ${isProduct ? 200 : 600} từ.`
      );
    }

    // --- Additional Group ---

    // keywordInSubheadings (max 3) - excluded on product / isPostsPage
    if (!excludeProduct && !isPostsPage) {
      let kwInSub = false;
      if (hasKw && content) {
        const kwWords = getWords(keyword.toLowerCase()).map(w => escapeRegex(normalizeQuotes(w)));
        if (kwWords.length > 0) {
          const subPattern = new RegExp('<h[2-6][^>]*>[\\s\\S]*?' + kwWords.join('[\\s\\W]+') + '[\\s\\S]*?</h[2-6]>', 'gi');
          kwInSub = subPattern.test(contentLower);
        }
      }
      record(
        'keywordInSubheadings',
        kwInSub,
        kwInSub ? 3 : 0,
        3,
        kwInSub ? 'Đã tìm thấy Từ khoá chính trong thẻ tiêu đề phụ (subheading).' : 'Dùng Từ khoá chính trong các tiêu đề phụ như H2, H3, H4...',
        'Khuyến nghị đưa từ khoá chính vào một hoặc nhiều tiêu đề phụ trong nội dung.'
      );
    }

    // keywordInImageAlt (max 2)
    let kwInImgAlt = false;
    if (hasKw) {
      const kwLower = keyword.toLowerCase();
      const altLower = thumbnailAlt.toLowerCase();
      if (altLower && (altLower === kwLower || altLower.includes(kwLower))) {
        kwInImgAlt = true;
      } else {
        const altRegex = /<img[^>]*\salt=(["'])(.*?)\1/gi;
        const alts = [];
        let imgM;
        while ((imgM = altRegex.exec(contentLower)) !== null) {
          alts.push(imgM[2]);
        }
        const kwCleanPattern = normalizeQuotes(Array.from(new Set(kwLower.split(' '))).join(' '))
          .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
          .replace(/ /g, '.*');
        const re = new RegExp(kwCleanPattern, 'gi');
        for (const a of alts) {
          if (re.test(a)) {
            kwInImgAlt = true;
            break;
          }
        }
        if (!kwInImgAlt && /\[gallery( [^\]]+?)?\]/gi.test(content)) {
          kwInImgAlt = true;
        }
      }
    }
    record(
      'keywordInImageAlt',
      kwInImgAlt,
      kwInImgAlt ? 2 : 0,
      2,
      kwInImgAlt ? 'Tìm thấy Từ khoá chính trong thuộc tính Alt của hình ảnh.' : 'Thêm hình ảnh với Từ khoá chính trong thẻ alt.',
      'Khuyến nghị chèn từ khoá chính vào văn bản thay thế (alt) của ít nhất một hình ảnh.'
    );

    // keywordDensity (max 6) - excluded on isPostsPage
    if (!isPostsPage) {
      const wc = wordCount(contentLower);
      let kwOccurrences = 0;
      let densityScore = 0;
      let densityOk = false;

      if (hasKw && wc > 0) {
        const kwClean = getWords(normalizeQuotes(keyword.toLowerCase())).join(' ');
        const cWords = getWords(cleanTextForContent(content));
        if (cWords.length > 0 && kwClean) {
          const joinedC = cWords.join(' ');
          const re = new RegExp(escapeRegex(kwClean), 'gi');
          const m = joinedC.match(re);
          kwOccurrences = m ? m.length : 0;
          const density = parseFloat(((kwOccurrences / wc) * 100).toFixed(2));
          if (density >= 0.5 && density <= 2.5) {
            densityOk = true;
            if (density < 0.76) densityScore = 2;
            else if (density <= 1.0) densityScore = 3;
            else densityScore = 6;
          }
        }
      }
      record(
        'keywordDensity',
        densityOk,
        densityScore,
        6,
        densityOk ? 'Mật độ từ khoá (Keyword Density) đạt mức lý tưởng.' : 'Hãy nhắm đến khoảng 1% mật độ từ khoá.',
        'Không có tỷ lệ phần trăm lý tưởng duy nhất, nhưng không nên nhồi nhét từ khoá quá dày đặc.'
      );
    }

    // lengthPermalink (max 4) - excluded on isHome
    if (!isHome) {
      const permalinkLen = permalink.length;
      const permalinkOk = permalinkLen > 0 && permalinkLen <= 75;
      record(
        'lengthPermalink',
        permalinkOk,
        permalinkOk ? 4 : 0,
        4,
        permalinkOk ? `Độ dài URL là ${permalinkLen} ký tự. Rất tốt!` : 'Đường dẫn tĩnh chỉ nên dài tối đa 75 ký tự.',
        'Hãy giữ URL ngắn gọn, súc tích.'
      );
    }

    // Link stats (linksHasExternals, linksNotAllExternals, linksHasInternal)
    // Excluded on product / isHome / isPostsPage
    if (!excludeProduct && !isHome && !isPostsPage) {
      const linkStats = getLinkStats(content, this.options.parentDomain);

      // linksHasExternals (max 4)
      const hasExt = linkStats.externalTotal > 0;
      record(
        'linksHasExternals',
        hasExt,
        hasExt ? 4 : 0,
        4,
        hasExt ? 'Tuyệt vời! Bạn đang trỏ liên kết đến các nguồn ngoài uy tín.' : 'Chưa tìm thấy liên kết ngoài nào. Hãy trỏ liên kết đến các nguồn tham khảo bên ngoài.',
        'Thêm liên kết trỏ ra bên ngoài.'
      );

      // linksNotAllExternals (max 2)
      const doFollowOk = linkStats.externalDofollow > 0;
      record(
        'linksNotAllExternals',
        doFollowOk,
        doFollowOk ? 2 : 0,
        2,
        doFollowOk ? 'Ít nhất một liên kết ngoài là DoFollow.' : 'Thêm liên kết DoFollow trỏ đến các tài nguyên bên ngoài.',
        'Nên có liên kết ngoài dạng dofollow.'
      );

      // linksHasInternal (max 5)
      const hasInt = linkStats.internalTotal > 0;
      record(
        'linksHasInternal',
        hasInt,
        hasInt ? 5 : 0,
        5,
        hasInt ? 'Bạn đang liên kết đến các bài viết khác trên website, rất tốt.' : 'Không tìm thấy liên kết nội bộ (internal link) nào trong bài viết.',
        'Thêm liên kết nội bộ.'
      );
    }

    // keywordNotUsed (max 0)
    record(
      'keywordNotUsed',
      isKeywordNew,
      0,
      0,
      isKeywordNew ? 'Bạn chưa từng dùng Từ khoá chính này trước đây.' : 'Bạn đã từng sử dụng Từ khoá chính này cho một bài viết khác.',
      ''
    );

    // --- Title Readability Group ---

    // titleStartWithKeyword (max 3)
    let titleStart = false;
    if (hasKw && titleWords.length > 0) {
      const kwWords = getWords(normalizeQuotes(keyword.toLowerCase()));
      const kwStr = kwWords.join(' ');
      const tStr = titleWords.join(' ');
      const idx = tStr.indexOf(kwStr);
      const halfLen = Math.floor(tStr.length / 2);
      titleStart = idx >= 0 && idx < halfLen;
    }
    record(
      'titleStartWithKeyword',
      titleStart,
      titleStart ? 3 : 0,
      3,
      titleStart ? 'Từ khoá chính xuất hiện ở đầu Tiêu đề SEO.' : 'Hãy đặt Từ khoá chính gần đầu Tiêu đề SEO.',
      'Tiêu đề SEO nên chứa Từ khoá chính ở phần đầu để thu hút click.'
    );

    // titleSentiment (max 1) - ONLY on isEn, excluded on product / isHome
    if (!excludeProduct && !isHome && isEn) {
      const sentScore = calculateSentiment(titleWords);
      const hasSentiment = sentScore !== 0;
      record(
        'titleSentiment',
        hasSentiment,
        hasSentiment ? 1 : 0,
        1,
        hasSentiment ? 'Tiêu đề của bạn thể hiện cảm xúc rõ ràng (tích cực hoặc tiêu cực).' : 'Tiêu đề của bạn chưa chứa từ ngữ biểu đạt cảm xúc rõ ràng.',
        'Tiêu đề gợi cảm xúc mạnh thường nhận được nhiều lượt nhấp chuột hơn.'
      );
    }

    // titleHasPowerWords (max 1) - ONLY included if powerWords array is non-empty, excluded on isHome
    if (!isHome && this.options.powerWords && this.options.powerWords.length > 0) {
      const rawTitleWords = titleLower.split(' ').map(trimPunctuation).filter(Boolean);
      const pwList = this.options.powerWords;
      const hasPower = rawTitleWords.some(w =>
        pwList.some(pw => w === pw || (w.startsWith(pw) && w.length - pw.length <= 4))
      );
      record(
        'titleHasPowerWords',
        hasPower,
        hasPower ? 1 : 0,
        1,
        hasPower ? 'Tiêu đề có chứa Từ khoá quyền lực (Power Word). Tuyệt vời!' : 'Tiêu đề chưa chứa Từ khoá quyền lực. Hãy thêm ít nhất một từ.',
        'Power Words là những từ thôi miên được các copywriter sử dụng để tăng tỷ lệ nhấp.'
      );
    }

    // titleHasNumber (max 1) - excluded on product / isHome
    if (!excludeProduct && !isHome) {
      const hasNumber = /\d+/.test(title);
      record(
        'titleHasNumber',
        hasNumber,
        hasNumber ? 1 : 0,
        1,
        hasNumber ? 'Bạn đang sử dụng số trong Tiêu đề SEO.' : 'Tiêu đề SEO của bạn chưa chứa con số nào.',
        'Tiêu đề chứa con số có tỷ lệ tạo lượt click cao hơn khoảng 36%.'
      );
    }

    // --- Content Readability Group ---

    // contentHasTOC (max 2) - excluded on product / isHome / isPostsPage
    if (!excludeProduct && !isHome && !isPostsPage) {
      const tocDetected = hasTOC || contentLower.includes('wp-block-rank-math-toc-block') || contentLower.includes('table-of-contents') || contentLower.includes('class="toc');
      record(
        'contentHasTOC',
        tocDetected,
        tocDetected ? 2 : 0,
        2,
        tocDetected ? 'Bạn đang sử dụng Mục lục (TOC) để phân đoạn nội dung.' : 'Hãy sử dụng Mục lục (TOC) để phân đoạn nội dung rõ ràng hơn.',
        'Mục lục giúp người đọc dễ nắm bắt cấu trúc và duyệt nội dung nhanh chóng.'
      );
    }

    // contentHasShortParagraphs (max 3) - excluded on isPostsPage
    if (!isPostsPage) {
      const pList = getParagraphs(content);
      const anyLong = pList.some(p => p.wordCount > 120);
      const hasShort = !anyLong;
      record(
        'contentHasShortParagraphs',
        hasShort,
        hasShort ? 3 : 0,
        3,
        hasShort ? 'Bạn đang sử dụng các đoạn văn ngắn gọn, dễ đọc.' : 'Có ít nhất một đoạn văn quá dài (>120 từ). Cân nhắc ngắt thành các đoạn ngắn.',
        'Các đoạn văn ngắn giúp mắt không bị mỏi và dễ theo dõi trên di động.'
      );
    }

    // contentHasAssets (max 6)
    const hasAnyText = content.trim().length > 0;
    const hasThumb = Boolean(thumbnail || thumbnailAlt);
    let assetScore = 0;
    if (hasAnyText) {
      const imgMatches = (content.match(/<img(?:[^>]+)?>/gi) || []).length + (content.match(/\[gallery( [^\]]+?)?\]/gi) || []).length;
      const totalImages = imgMatches + (hasThumb ? 1 : 0) + images.length;
      let imgScore = 0;
      if (totalImages >= 4) imgScore = 6;
      else if (totalImages === 3) imgScore = 4;
      else if (totalImages === 2) imgScore = 2;
      else if (totalImages === 1) imgScore = 1;

      const vidMatches = (content.match(/<(?:video|iframe)[^>]*>/gi) || []).length + videos.length;
      let vidScore = 0;
      if (vidMatches >= 2) vidScore = 2;
      else if (vidMatches === 1) vidScore = 1;

      assetScore = Math.min(6, imgScore + vidScore);
    } else if (hasThumb) {
      assetScore = 1;
    }
    const assetOk = assetScore >= 1;
    record(
      'contentHasAssets',
      assetOk,
      assetScore,
      6,
      assetOk ? 'Nội dung có chứa hình ảnh và/hoặc video.' : 'Thêm vài hình ảnh hoặc video để nội dung sinh động hơn.',
      'Bài viết có hình ảnh và video luôn thu hút người đọc tốt hơn.'
    );

    // --- Product Specific Tests (RM Pro) ---
    if (isProduct) {
      // hasProductSchema: max 2
      const schemaOk = Boolean(this.options.hasProductSchema);
      record(
        'hasProductSchema',
        schemaOk,
        schemaOk ? 2 : 0,
        2,
        schemaOk ? 'Bạn đang sử dụng Schema Sản phẩm (Product Schema) cho sản phẩm này.' : 'Sản phẩm này chưa áp dụng Schema Sản phẩm (Product Schema).',
        'Schema Product giúp Google hiển thị giá và tình trạng còn hàng trên trang tìm kiếm.'
      );

      // isReviewEnabled: max 2
      const reviewOk = Boolean(this.options.isReviewEnabled);
      record(
        'isReviewEnabled',
        reviewOk,
        reviewOk ? 2 : 0,
        2,
        reviewOk ? 'Đánh giá nhận xét (Reviews) đã được bật cho sản phẩm này. Tuyệt vời!' : 'Tính năng đánh giá nhận xét (Reviews) đang bị tắt cho sản phẩm này.',
        'Đánh giá sao giúp gia tăng độ tin cậy và CTR tìm kiếm.'
      );
    }

    return this.finalize(totalAchieved, totalMax, results);
  }

  finalize(achieved, max, results) {
    let finalScore = 0;
    if (max > 0) {
      finalScore = Math.round((achieved / max) * 100);
      if (finalScore > 100) finalScore = 100;
      if (finalScore < 0) finalScore = 0;
    }

    const output = {
      score: finalScore,
      achievedScore: achieved,
      maxScore: max,
      results,
    };

    if (typeof window !== 'undefined') {
      window.lacaSeoAnalysis = output;
    }

    return output;
  }
}
