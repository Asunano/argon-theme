/**
 * argon/checkbox —— 单个复选框
 *
 * 对应短代码 [checkbox checked="true" inline="true"]内容[/checkbox]
 *
 * 短代码的输出是：
 *   <div class='shortcode-todo custom-control custom-checkbox[ inline]'>
 *     <input class='custom-control-input' type='checkbox'[ checked]>
 *     <label class='custom-control-label'><span>内容</span></label>
 *   </div>
 *
 * 与 argon/todolist 的区别：todolist 是一个区块里放多条清单项，
 * 适合整段待办；这个是一次只放一条，适合正文里夹一个勾选项，
 * 并且支持 inline（与前后文字排在同一行）—— 这是 todolist 没有的。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, ToggleControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/checkbox', {
	apiVersion: 2,
	title: __('复选框'),
	icon: 'yes-alt',
	category: 'argon',
	keywords: [ 'argon', __('复选框'), 'checkbox', 'todo' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		checked: {
			type: 'boolean',
			default: false,
		},
		inline: {
			type: 'boolean',
			default: false,
		},
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const cls = [
			'shortcode-todo',
			'custom-control',
			'custom-checkbox',
			props.attributes.inline ? 'inline' : '',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<div>
				<div className={cls}>
					<input
						className="custom-control-input"
						type="checkbox"
						checked={props.attributes.checked}
						readOnly
					/>
					<label className="custom-control-label">
						<RichText
							tagName="span"
							placeholder={__('条目内容')}
							value={props.attributes.content}
							onChange={(value) => props.setAttributes({ content: value })}
						/>
					</label>
				</div>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<ToggleControl
										label={__('默认勾选')}
										checked={props.attributes.checked}
										onChange={(value) => props.setAttributes({ checked: value })}
									/>
								</fieldset>
								<fieldset>
									<ToggleControl
										label={__('行内显示')}
										checked={props.attributes.inline}
										onChange={(value) => props.setAttributes({ inline: value })}
									/>
									<p className="help-text">{__('打开后与前后文字排在同一行，可连续放几个做行内多选')}</p>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		const cls = [
			'shortcode-todo',
			'custom-control',
			'custom-checkbox',
			props.attributes.inline ? 'inline' : '',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<div className={argonBlockClass(cls, props)} {...argonBlockAnchor(props)}>
				<input
					className="custom-control-input"
					type="checkbox"
					checked={props.attributes.checked ? true : null}
					readOnly
				/>
				<label className="custom-control-label">
					<span dangerouslySetInnerHTML={{ __html: props.attributes.content }} />
				</label>
			</div>
		);
	},
});
