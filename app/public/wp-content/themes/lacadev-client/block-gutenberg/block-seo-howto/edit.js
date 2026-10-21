import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, Button, TextControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps({ className: 'lacadev-seo-howto' });
  const { title, description, totalTime, steps } = attributes;

  const updateStep = (index, field, val) => {
    const next = [...steps];
    next[index] = { ...next[index], [field]: val };
    setAttributes({ steps: next });
  };

  const removeStep = (index) => {
    setAttributes({ steps: steps.filter((_, i) => i !== index) });
  };

  const addStep = () => {
    setAttributes({
      steps: [
        ...steps,
        {
          id: 'step-' + Date.now(),
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
        <PanelBody title={__('Cài đặt HowTo', 'laca')} initialOpen={true}>
          <TextControl
            label={__('Tổng thời gian (ISO 8601, vd PT30M)', 'laca')}
            value={totalTime}
            onChange={(v) => setAttributes({ totalTime: v })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <RichText
          tagName="h2"
          className="lacadev-howto-title"
          value={title}
          onChange={(v) => setAttributes({ title: v })}
          placeholder={__('Tiêu đề hướng dẫn (How To)…', 'laca')}
        />
        <RichText
          tagName="p"
          className="lacadev-howto-desc"
          value={description}
          onChange={(v) => setAttributes({ description: v })}
          placeholder={__('Mô tả tổng quan…', 'laca')}
        />

        <div className="lacadev-howto-steps">
          {steps.map((s, idx) => (
            <div key={idx} className="lacadev-howto-step" style={{ marginBottom: 16 }}>
              <RichText
                tagName="h3"
                className="lacadev-howto-step-title"
                value={s.title}
                onChange={(v) => updateStep(idx, 'title', v)}
                placeholder={__('Tiêu đề bước…', 'laca')}
              />
              <RichText
                tagName="p"
                className="lacadev-howto-step-content"
                value={s.content}
                onChange={(v) => updateStep(idx, 'content', v)}
                placeholder={__('Nội dung chi tiết bước…', 'laca')}
              />
              <Button
                variant="secondary"
                isDestructive
                isSmall
                onClick={() => removeStep(idx)}
              >
                {__('Xóa bước', 'laca')}
              </Button>
            </div>
          ))}
          <Button variant="primary" onClick={addStep} style={{ marginTop: 8 }}>
            {__('+ Thêm bước', 'laca')}
          </Button>
        </div>
      </div>
    </>
  );
}
