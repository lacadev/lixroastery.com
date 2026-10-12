/**
 * archive-journal.php (/journal) — bấm tab danh mục CẤP 1 sẽ đổi phần
 * "lưới danh mục con" bên dưới (KHÔNG phải bài viết, xem
 * laca_journal_render_category_directory()). Mọi panel đã được render sẵn
 * ở server nên đổi tab chỉ là ẩn/hiện — tức thì, không AJAX.
 * Tách riêng khỏi cpt-grid.js vì nội dung khác hẳn (lưới danh mục,
 * không phải lưới bài viết).
 */

function initJournalDirectory( root ) {
	const tabs = root.querySelectorAll( '.block-cpt-grid__tab' );
	const panels = root.querySelectorAll( '.journal-directory' );

	if ( ! tabs.length || ! panels.length ) {
		return;
	}

	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			if ( tab.classList.contains( 'is-active' ) ) {
				return;
			}

			const slug = tab.dataset.termSlug || '';

			tabs.forEach( ( t ) => {
				const isActive = t === tab;
				t.classList.toggle( 'is-active', isActive );
				t.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			panels.forEach( ( panel ) => {
				panel.hidden = panel.dataset.termSlug !== slug;
			} );
		} );
	} );
}

/**
 * taxonomy-journal-cat.php (/journal-cat/{slug}) — lưới bài viết đổi qua
 * cpt-grid.js; ở đây chỉ đồng bộ tiêu đề + mô tả + URL theo tab vừa chọn
 * (nghe event "cptgrid:tabchange"), nếu không header vẫn giữ danh mục cũ.
 *
 * @param {HTMLElement} root .block-cpt-grid__inner của trang taxonomy.
 */
function initJournalCatHeader( root ) {
	const section = root.closest( '.journal-archive' );
	const titleEl =
		section && section.querySelector( '.journal-archive__title' );
	const descEl = section && section.querySelector( '.journal-archive__desc' );

	root.addEventListener( 'cptgrid:tabchange', ( e ) => {
		const { tab, termSlug } = e.detail;
		const newName = tab.dataset.termName || '';

		if ( titleEl && newName ) {
			const oldName = titleEl.textContent.trim();
			if ( oldName && document.title.includes( oldName ) ) {
				document.title = document.title.replace( oldName, newName );
			}
			titleEl.textContent = newName;
		}

		if ( descEl ) {
			const tpl = Array.from(
				root.querySelectorAll( 'template[data-term-desc]' )
			).find( ( t ) => t.dataset.termDesc === termSlug );
			const html = tpl ? tpl.innerHTML.trim() : '';
			descEl.innerHTML = html;
			descEl.hidden = ! html;
		}

		// replaceState (không pushState) để reload/chia sẻ link ra đúng tab
		// mà không làm nút Back phải đi qua từng tab đã bấm.
		if ( tab.dataset.termUrl ) {
			window.history.replaceState( null, '', tab.dataset.termUrl );
		}
	} );
}

function initAllJournalDirectories() {
	document
		.querySelectorAll( '[data-journal-directory]' )
		.forEach( initJournalDirectory );
	document
		.querySelectorAll( '[data-journal-cat-header]' )
		.forEach( initJournalCatHeader );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllJournalDirectories );
} else {
	initAllJournalDirectories();
}
