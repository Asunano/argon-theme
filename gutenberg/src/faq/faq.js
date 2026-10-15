/**
 * argon/faq —— 常见问题（FAQ）
 *
 * 前台输出两样东西：
 *   1. 可见的问答列表 —— 用原生 <details>/<summary>，语义正确、可键盘操作、不依赖 JS
 *   2. FAQPage 结构化数据（JSON-LD）—— 由 PHP 侧统一收集、在页面底部输出【一段】，
 *      避免一个页面出现多段 FAQPage（Google 不推荐）
 *
 * 与 argon/howto 一样是动态区块：JSON-LD 要合并输出，静态保存做不到。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl, Button } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

const DEFAULT_ITEM = { question: '', answer: '' };

registerBlockType('argon/faq', {
	apiVersion: 2,
	title: __('常见问题 FAQ'),
	icon: 'editor-help',
	category: 'argon',
	keywords: [ 'argon', __('FAQ'), __('常见问题'), 'faq', 'schema' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		title: {
			type: 'string',
			default: '',
		},
		items: {
			type: 'array',
			default: [ { ...DEFAULT_ITEM } ],
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const items = props.attributes.items || [];

		const updateItem = (index, key, value) => {
			const next = items.map((it, i) => (i === index ? { ...it, [key]: value } : it));
			props.setAttributes({ items: next });
		};
		const addItem = () => {
			props.setAttributes({ items: [ ...items, { ...DEFAULT_ITEM } ] });
		};
		const removeItem = (index) => {
			if (items.length <= 1) {
				return;
			}
			props.setAttributes({ items: items.filter((it, i) => i !== index) });
		};

		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<TextControl
										label={__('标题')}
										value={props.attributes.title}
										onChange={(value) => props.setAttributes({ title: value })}
									/>
									<p className="help-text">{__('显示在问答列表上方的标题，留空则不显示')}</p>
								</fieldset>
								{items.map((item, index) => (
									<fieldset key={index}>
										<PanelRow>{__('问题') + ' ' + (index + 1)}</PanelRow>
										<TextControl
											value={item.question}
											onChange={(value) => updateItem(index, 'question', value)}
										/>
										<PanelRow>{__('回答')}</PanelRow>
										<TextControl
											value={item.answer}
											onChange={(value) => updateItem(index, 'answer', value)}
										/>
										<Button
											isDestructive
											variant="secondary"
											onClick={() => removeItem(index)}
											disabled={items.length <= 1}
										>
											{__('移除此项')}
										</Button>
									</fieldset>
								))}
								<Button variant="primary" onClick={addItem}>
									{__('+ 添加问题')}
								</Button>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/faq" attributes={props.attributes} />
			</div>
		);
	},
	/* 动态区块：JSON-LD 需要按页面合并，静态保存做不到 */
	save: () => null,
});
