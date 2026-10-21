import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, Button, SelectControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps({ className: 'lacadev-seo-faq' });
  const { title, titleWrapper, listStyle, questions } = attributes;

  const updateQuestion = (index, field, val) => {
    const next = [...questions];
    next[index] = { ...next[index], [field]: val };
    setAttributes({ questions: next });
  };

  const removeQuestion = (index) => {
    setAttributes({ questions: questions.filter((_, i) => i !== index) });
  };

  const addQuestion = () => {
    setAttributes({
      questions: [
        ...questions,
        {
          id: 'faq-' + Date.now(),
          title: '',
          content: '',
          visible: true,
        },
      ],
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Cài đặt FAQ', 'laca')} initialOpen={true}>
          <SelectControl
            label={__('Thẻ tiêu đề câu hỏi', 'laca')}
            value={titleWrapper}
            options={[
              { label: 'H2', value: 'h2' },
              { label: 'H3', value: 'h3' },
              { label: 'H4', value: 'h4' },
              { label: 'H5', value: 'h5' },
              { label: 'H6', value: 'h6' },
              { label: 'P', value: 'p' },
            ]}
            onChange={(v) => setAttributes({ titleWrapper: v })}
          />
          <SelectControl
            label={__('Kiểu danh sách', 'laca')}
            value={listStyle}
            options={[
              { label: __('Không có (Mặc định)', 'laca'), value: 'none' },
              { label: __('Số thứ tự (OL)', 'laca'), value: 'numbered' },
              { label: __('Dấu chấm (UL)', 'laca'), value: 'unordered' },
            ]}
            onChange={(v) => setAttributes({ listStyle: v })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <RichText
          tagName="h2"
          className="lacadev-faq-main-title"
          value={title}
          onChange={(v) => setAttributes({ title: v })}
          placeholder={__('Tiêu đề FAQ…', 'laca')}
        />
        <div className="lacadev-faq-list">
          {questions.map((q, idx) => (
            <div key={idx} className="lacadev-faq-item" style={{ marginBottom: 16 }}>
              <RichText
                tagName={titleWrapper || 'h3'}
                className="lacadev-faq-question"
                value={q.title}
                onChange={(v) => updateQuestion(idx, 'title', v)}
                placeholder={__('Nhập câu hỏi…', 'laca')}
              />
              <RichText
                tagName="p"
                className="lacadev-faq-answer"
                value={q.content}
                onChange={(v) => updateQuestion(idx, 'content', v)}
                placeholder={__('Nhập câu trả lời…', 'laca')}
              />
              <Button
                variant="secondary"
                isDestructive
                isSmall
                onClick={() => removeQuestion(idx)}
              >
                {__('Xóa câu hỏi', 'laca')}
              </Button>
            </div>
          ))}
          <Button variant="primary" onClick={addQuestion} style={{ marginTop: 8 }}>
            {__('+ Thêm câu hỏi', 'laca')}
          </Button>
        </div>
      </div>
    </>
  );
}
