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

function initAllJournalDirectories() {
	document
		.querySelectorAll( '[data-journal-directory]' )
		.forEach( initJournalDirectory );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllJournalDirectories );
} else {
	initAllJournalDirectories();
}
