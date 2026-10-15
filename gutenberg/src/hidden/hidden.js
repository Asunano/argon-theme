/**
 * argon/hidden —— 隐藏文本（鼠标移上去才显示）
 *
 * 对应短代码 [hidden tip="提示文字" type="blur"]内容[/hidden]
 *
 * 短代码的输出是：
 *   <span class='argon-hidden-text argon-hidden-text-blur' title='提示'>内容</span>
 *   <span class='argon-hidden-text argon-hidden-text-background' title='提示'>内容</span>
 * 区块用完全相同的类名，前端样式直接复用 style.css 里的那套。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/hidden', {
	apiVersion: 2,
	title: __('隐藏文本'),
	icon: 'hidden',
	category: 'argon',
	keywords: [ 'argon', __('隐藏文本'), 'hidden', 'spoiler' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		type: {
			type: 'string',
			default: 'blur',
		},
		tip: {
			type: 'string',
			default: '',
		},
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const hiddenClass = [
			'argon-hidden-text',
			props.attributes.type === 'background'
				? 'argon-hidden-text-background'
				: 'argon-hidden-text-blur',
		].join(' ');

		return (
			<div>
				<span className={hiddenClass} title={props.attributes.tip || undefined}>
					<RichText
						tagName="span"
						placeholder={__('隐藏的内容')}
						value={props.attributes.content}
						onChange={(value) => props.setAttributes({ content: value })}
					/>
				</span>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<SelectControl
										label={__('显示方式')}
										value={props.attributes.type}
										options={[
											{ label: __('模糊（鼠标移上变清晰）'), value: 'blur' },
											{ label: __('色块遮挡'), value: 'background' },
										]}
										onChange={(value) => props.setAttributes({ type: value })}
									/>
								</fieldset>
								<fieldset>
									<TextControl
										label={__('悬停提示')}
										value={props.attributes.tip}
										onChange={(value) => props.setAttributes({ tip: value })}
									/>
									<p className="help-text">{__('鼠标移上去时显示的原生提示，留空则不显示')}</p>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		const hiddenClass = [
			'argon-hidden-text',
			props.attributes.type === 'background'
				? 'argon-hidden-text-background'
				: 'argon-hidden-text-blur',
		].join(' ');

		return (
			<span
				className={argonBlockClass(hiddenClass, props)}
				{...argonBlockAnchor(props)}
				title={props.attributes.tip || undefined}
			>
				<span dangerouslySetInnerHTML={{ __html: props.attributes.content }} />
			</span>
		);
	},
});
