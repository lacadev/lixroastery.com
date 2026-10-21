import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps({ className: 'lacadev-seo-toc' });
  const { title, titleWrapper, listStyle } = attributes;

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Cài đặt Mục lục (TOC)', 'laca')} initialOpen={true}>
          <SelectControl
            label={__('Thẻ tiêu đề', 'laca')}
            value={titleWrapper}
            options={[
              { label: 'H2', value: 'h2' },
              { label: 'H3', value: 'h3' },
              { label: 'H4', value: 'h4' },
              { label: 'H5', value: 'h5' },
              { label: 'H6', value: 'h6' },
              { label: 'P', value: 'p' },
              { label: 'DIV', value: 'div' },
            ]}
            onChange={(v) => setAttributes({ titleWrapper: v })}
          />
          <SelectControl
            label={__('Kiểu danh sách', 'laca')}
            value={listStyle}
            options={[
              { label: __('Dấu chấm (UL)', 'laca'), value: 'ul' },
              { label: __('Số thứ tự (OL)', 'laca'), value: 'ol' },
            ]}
            onChange={(v) => setAttributes({ listStyle: v })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <RichText
          tagName={titleWrapper || 'h2'}
          className="lacadev-toc-title"
          value={title}
          onChange={(v) => setAttributes({ title: v })}
          placeholder={__('Tiêu đề mục lục…', 'laca')}
        />
        <nav>
          <p style={{ color: '#6b7280', fontSize: '13px', fontStyle: 'italic' }}>
            {__('Mục lục sẽ tự động quét danh sách heading trong bài viết ngoài frontend.', 'laca')}
          </p>
        </nav>
      </div>
    </>
  );
}
