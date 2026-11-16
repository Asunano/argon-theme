/**
 * argon/mark —— 高亮标记
 *
 * 对应短代码 [mark color="indigo" style="underline"]文字[/mark]
 *
 * 颜色与样式类（argon-mark-*）由主题 style.css 提供：
 *   颜色类只负责定义 --mark-color 变量（黄/靛/绿/红/橙/蓝/灰），
 *   样式类（flat 平铺 / underline 底部色条 / wavy 波浪线）消费它，二者可自由组合。
 * 因此区块不写行内样式 —— 与短代码产物完全一致，暗色模式也能跟随主题。
 * 颜色用「名字下拉框」而不是取色器：任意 hex 无法映射成 --mark-color 变量。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

const COLOR_OPTIONS = [
	{ label: __('黄色'), value: 'yellow' },
	{ label: __('靛蓝'), value: 'indigo' },
	{ label: __('绿色'), value: 'green' },
	{ label: __('红色'), value: 'red' },
	{ label: __('橙色'), value: 'orange' },
	{ label: __('蓝色'), value: 'blue' },
	{ label: __('灰色'), value: 'grey' },
];

const STYLE_OPTIONS = [
	{ label: __('平铺'), value: 'flat' },
	{ label: __('底部色条'), value: 'underline' },
	{ label: __('波浪线'), value: 'wavy' },
];

const markClass = (color, style) => {
	const colorName = COLOR_OPTIONS.some((o) => o.value === color) ? color : 'yellow';
	const styleName = STYLE_OPTIONS.some((o) => o.value === style) ? style : 'flat';
	return 'argon-mark argon-mark-' + colorName + ' argon-mark-' + styleName;
};

registerBlockType('argon/mark', {
	apiVersion: 2,
	title: __('高亮标记'),
	icon: 'editor-textcolor',
	category: 'argon',
	keywords: [ 'argon', __('高亮'), 'mark', 'highlight' ],
	supports: {
		anchor: true,
		className: true,
	},
	attributes: {
		color: {
			type: 'string',
			default: 'yellow',
		},
		style: {
			type: 'string',
			default: 'flat',
		},
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		return (
			<div>
				<mark className={markClass(props.attributes.color, props.attributes.style)}>
					<RichText
						tagName="span"
						placeholder={__('要标记的文字')}
						value={props.attributes.content}
						onChange={(value) => props.setAttributes({ content: value })}
					/>
				</mark>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('颜色')}
									value={props.attributes.color}
									options={COLOR_OPTIONS}
									onChange={(value) => props.setAttributes({ color: value })}
								/>
							</fieldset>
							<fieldset>
								<SelectControl
									label={__('样式')}
									value={props.attributes.style}
									options={STYLE_OPTIONS}
									onChange={(value) => props.setAttributes({ style: value })}
								/>
							</fieldset>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		return (
			<mark
				className={argonBlockClass(markClass(props.attributes.color, props.attributes.style), props)}
				{...argonBlockAnchor(props)}
			>
				<span dangerouslySetInnerHTML={{ __html: props.attributes.content }} />
			</mark>
		);
	},
});
