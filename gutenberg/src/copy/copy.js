/**
 * argon/copy —— 复制按钮
 *
 * 对应短代码 [copy text="实际复制的内容"]显示的文字[/copy]
 *
 * 静态区块：save 输出与短代码相同的 .argon-copy 结构。
 * 「复制内容」留空时不在 HTML 里写 data-copy，由前端点击处理器回落到
 * 显示文本本身——与短代码的 strip_tags 兜底行为一致，但避免了在 save 阶段
 * 做不可靠的 HTML 剥离。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/copy', {
	apiVersion: 2,
	title: __('复制按钮'),
	icon: 'clipboard',
	category: 'argon',
	keywords: [ 'argon', __('复制'), 'copy', 'clipboard' ],
	supports: {
		anchor: true,
		className: true,
	},
	attributes: {
		content: {
			type: 'string',
			default: '',
		},
		text: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const a = props.attributes;
		return (
			<div>
				<span className="argon-copy">
					<code className="argon-copy-text" style={{ background: 'transparent' }}>
						<RichText
							tagName="span"
							placeholder={__('要复制的文本')}
							value={a.content}
							onChange={(value) => props.setAttributes({ content: value })}
						/>
					</code>
					<span className="argon-copy-btn" style={{ padding: '2px 8px' }}>
						<i className="fa fa-clipboard"></i>
					</span>
				</span>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('复制内容')}
								help={__('需要复制与显示内容不同的文本时填写（例如显示命令、复制完整命令）；留空则复制显示的文本')}
								value={a.text}
								onChange={(value) => props.setAttributes({ text: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		const a = props.attributes;
		return (
			<span className={argonBlockClass('argon-copy', props)} {...argonBlockAnchor(props)}>
				<code className="argon-copy-text">
					<span dangerouslySetInnerHTML={{ __html: a.content }} />
				</code>
				<button
					type="button"
					className="argon-copy-btn"
					data-copy={a.text !== '' ? a.text : undefined}
					data-copied-label={__('已复制', 'argon')}
					aria-label={__('复制', 'argon')}
				>
					<i className="fa fa-clipboard"></i>
				</button>
			</span>
		);
	},
});
