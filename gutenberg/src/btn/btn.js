/**
 * argon/btn —— 按钮
 *
 * 对应短代码 [btn href="https://..." color="indigo" size="lg" shape="round" outline="true" icon="download" newtab="true"]文字[/btn]
 *
 * 复用主题自带 Bootstrap 的 btn-* 类（前台外观与主题按钮一致），
 * 颜色映射与 [label] 同款：indigo→primary green→success red→danger orange→warning blue→info。
 * 只有 btn-round（圆角胶囊）是主题在 style.css 新增的变体类。
 * 图标沿用 collapse 的约定：fa_icon_name 只存名字（如 download），渲染成 fa fa-download。
 */

import { __ } from './../i18n/i18n.js';
import { isWhitespaceCharacter } from 'is-whitespace-character';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

const COLOR_MAP = {
	indigo: 'primary',
	green: 'success',
	red: 'danger',
	orange: 'warning',
	blue: 'info',
};

const COLOR_OPTIONS = [
	{ label: __('靛蓝'), value: 'indigo' },
	{ label: __('绿色'), value: 'green' },
	{ label: __('红色'), value: 'red' },
	{ label: __('橙色'), value: 'orange' },
	{ label: __('蓝色'), value: 'blue' },
];

const btnClass = (attrs) => {
	const colorName = COLOR_MAP[attrs.color] || 'primary';
	const cls = [attrs.outline ? 'btn-outline-' + colorName : 'btn-' + colorName];
	if (attrs.size === 'sm' || attrs.size === 'lg') {
		cls.push('btn-' + attrs.size);
	}
	if (attrs.shape === 'round') {
		cls.push('btn-round');
	}
	return ['btn', ...cls].join(' ');
};

registerBlockType('argon/btn', {
	apiVersion: 2,
	title: __('按钮'),
	icon: 'button',
	category: 'argon',
	keywords: [ 'argon', __('按钮'), 'btn', 'button' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		href: {
			type: 'string',
			default: '',
		},
		text: {
			type: 'string',
			default: '',
		},
		color: {
			type: 'string',
			default: 'indigo',
		},
		outline: {
			type: 'boolean',
			default: false,
		},
		size: {
			type: 'string',
			default: '',
		},
		shape: {
			type: 'string',
			default: 'square',
		},
		fa_icon_name: {
			type: 'string',
			default: '',
		},
		newTab: {
			type: 'boolean',
			default: false,
		},
	},
	edit: (props) => {
		const { attributes } = props;
		return (
			<div>
				<a
					className={btnClass(attributes)}
					href={attributes.href || undefined}
					onClick={(e) => e.preventDefault()}
				>
					{!(isWhitespaceCharacter(attributes.fa_icon_name) || attributes.fa_icon_name == '') && (
						<i className={`fa fa-${attributes.fa_icon_name}`}></i>
					)}
					&nbsp;
					<RichText
						tagName="span"
						placeholder={__('按钮文字')}
						value={attributes.text}
						onChange={(value) => props.setAttributes({ text: value })}
					/>
				</a>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('链接地址')}
								value={attributes.href}
								onChange={(value) => props.setAttributes({ href: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('在新标签页打开')}
								checked={attributes.newTab}
								onChange={(value) => props.setAttributes({ newTab: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('图标')}
								help={__('Font Awesome 中的图标名，留空则不显示')}
								value={attributes.fa_icon_name}
								onChange={(value) => props.setAttributes({ fa_icon_name: value })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('颜色')}
									value={attributes.color}
									options={COLOR_OPTIONS}
									onChange={(value) => props.setAttributes({ color: value })}
								/>
							</fieldset>
							<fieldset>
								<SelectControl
									label={__('形状')}
									value={attributes.shape}
									options={[
										{ label: __('方形'), value: 'square' },
										{ label: __('圆角'), value: 'round' },
									]}
									onChange={(value) => props.setAttributes({ shape: value })}
								/>
							</fieldset>
							<fieldset>
								<SelectControl
									label={__('尺寸')}
									value={attributes.size}
									options={[
										{ label: __('标准'), value: '' },
										{ label: __('小'), value: 'sm' },
										{ label: __('大'), value: 'lg' },
									]}
									onChange={(value) => props.setAttributes({ size: value })}
								/>
							</fieldset>
							<fieldset>
								<ToggleControl
									label={__('描边样式')}
									checked={attributes.outline}
									onChange={(value) => props.setAttributes({ outline: value })}
								/>
							</fieldset>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		const { attributes } = props;
		return (
			<a
				href={attributes.href || undefined}
				target={attributes.newTab ? '_blank' : undefined}
				rel={attributes.newTab ? 'noopener' : undefined}
				className={argonBlockClass(btnClass(attributes), props)}
				{...argonBlockAnchor(props)}
			>
				{!(isWhitespaceCharacter(attributes.fa_icon_name) || attributes.fa_icon_name == '') && (
					<i className={`fa fa-${attributes.fa_icon_name}`}></i>
				)}
				&nbsp;
				<span dangerouslySetInnerHTML={{ __html: attributes.text }} />
			</a>
		);
	},
});
