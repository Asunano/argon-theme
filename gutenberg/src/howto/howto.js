/**
 * argon/howto —— 操作步骤（HowTo）
 *
 * 前台输出两样东西：
 *   1. 可见的步骤列表 —— 有序列表，带名称与说明
 *   2. HowTo 结构化数据（JSON-LD）—— 由 PHP 侧统一收集、在页面底部输出【一段】，
 *      避免一个页面出现多段 HowTo
 *
 * 与 argon/faq 一样是动态区块：JSON-LD 要合并输出，静态保存做不到。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl, TextareaControl, Button } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

const DEFAULT_STEP = { name: '', text: '' };

registerBlockType('argon/howto', {
	apiVersion: 2,
	title: __('操作步骤 HowTo'),
	icon: 'list-view',
	category: 'argon',
	keywords: [ 'argon', __('HowTo'), __('步骤'), __('教程'), 'howto', 'schema' ],
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
		description: {
			type: 'string',
			default: '',
		},
		steps: {
			type: 'array',
			default: [ { ...DEFAULT_STEP } ],
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const steps = props.attributes.steps || [];

		const updateStep = (index, key, value) => {
			const next = steps.map((s, i) => (i === index ? { ...s, [key]: value } : s));
			props.setAttributes({ steps: next });
		};
		const addStep = () => {
			props.setAttributes({ steps: [ ...steps, { ...DEFAULT_STEP } ] });
		};
		const removeStep = (index) => {
			if (steps.length <= 1) {
				return;
			}
			props.setAttributes({ steps: steps.filter((s, i) => i !== index) });
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
									<p className="help-text">{__('这份教程要做什么，例如「如何安装 yt6801 驱动」')}</p>
								</fieldset>
								<fieldset>
									<TextareaControl
										label={__('简介')}
										value={props.attributes.description}
										onChange={(value) => props.setAttributes({ description: value })}
									/>
								</fieldset>
								{steps.map((step, index) => (
									<fieldset key={index}>
										<PanelRow>{__('步骤') + ' ' + (index + 1)}</PanelRow>
										<TextControl
											label={__('步骤名')}
											value={step.name}
											onChange={(value) => updateStep(index, 'name', value)}
										/>
										<TextareaControl
											label={__('说明')}
											value={step.text}
											onChange={(value) => updateStep(index, 'text', value)}
										/>
										<Button
											isDestructive
											variant="secondary"
											onClick={() => removeStep(index)}
											disabled={steps.length <= 1}
										>
											{__('移除此项')}
										</Button>
									</fieldset>
								))}
								<Button variant="primary" onClick={addStep}>
									{__('+ 添加步骤')}
								</Button>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/howto" attributes={props.attributes} />
			</div>
		);
	},
	/* 动态区块：JSON-LD 需要按页面合并，静态保存做不到 */
	save: () => null,
});
