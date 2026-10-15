import './editor.scss';
import { argonBlockClass, argonBlockAnchor, argonColorClass, argonColorStyle } from './../block-utils.js';
import { ArgonColorControl } from './../components/color-control.js';
import { __ } from './../i18n/i18n.js';
import {
	RichText,
	MediaUpload,
	BlockControls,
	AlignmentToolbar,
	RichTextToolbarButton,
	InspectorControls,
	PanelColorSettings
} from "@wordpress/block-editor";
import {
	TextControl,
	Panel, PanelBody, PanelRow
} from '@wordpress/components';
import { isWhitespaceCharacter } from 'is-whitespace-character'

const { registerBlockType } = wp.blocks;

registerBlockType('argon/alert', {
	apiVersion: 2,
	title: __('提示'),
	icon: 'warning',
	category: 'argon',
	supports: {
		align: ['wide', 'full'],
		anchor: true,
		className: true,
	},
	keywords: [
		'argon',
		__('提示')
	],
	attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		content: {
			type: 'string',
			default: ''
		},
		fa_icon_name: {
			type: 'string',
			default: 'info-circle'
		},
	},
	edit: (props) => {
		const onChangeContent = (value) => {
			props.setAttributes({ content: value });
		};
		const onChangeColor = (value) => {
			props.setAttributes({ color: (value || "#7889e8") });
		}
		const onIconChange = (value) => {
			props.setAttributes({ fa_icon_name: value });
		}

		return (
			<div>
				<div className={argonBlockClass("alert", props)} {...argonBlockAnchor(props)} style={{ backgroundColor: props.attributes.color }}>
					{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
						<span className="alert-inner--icon">
							<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>
						</span>
					}
					<RichText
						tagName="span"
						className="alert-inner--text"
						placeholder={__("内容")}
						value={props.attributes.content}
						onChange={onChangeContent}
					/>
				</div>
				<InspectorControls key="setting">
					<PanelBody title={__("区块设置")} icon={"more"} initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<PanelRow>{__('颜色', 'argon')}</PanelRow>
									<ArgonColorControl value={props.attributes.color} onChange={onChangeColor} />
								</fieldset>
								<fieldset>
									<PanelRow>{__('图标', 'argon')}</PanelRow>
									<TextControl
										value={props.attributes.fa_icon_name}
										onChange={onIconChange}
									/>
									<p className="help-text">
										{__('Font Awesome 中的图标名，留空则不显示', 'argon')}&nbsp;
										<a href="https://fontawesome.com/v4.7.0/icons/" target="_blank">{__('浏览图标', 'argon')}</a>
									</p>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		/* 颜色改走类名。
		   短代码用的是 <div class='alert alert-primary'>，而区块一直用行内
		   background-color —— 行内样式优先级最高，主题定制和暗色模式都盖不住。
		   预设色映射成 alert-<name> 类，不再写行内样式；用户自己挑的任意色映射不到，
		   仍然保留行内样式，否则颜色会丢。 */
		const colorClass = argonColorClass('alert', props.attributes.color);
		return (
			<div className={argonBlockClass(`alert ${colorClass}`.trim(), props)} {...argonBlockAnchor(props)} style={argonColorStyle('alert', props.attributes.color, 'backgroundColor')}>
				{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
					<span className="alert-inner--icon">
						<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>
					</span>
				}
				<span className="alert-inner--text" dangerouslySetInnerHTML={{ __html: props.attributes.content }}></span>
			</div>
		);
	},
	/* 旧版 markup 声明。
	   阶段 3 之前，颜色是写死在行内 style 里的，根元素没有 alert-<name> 类。
	   把旧写法原样声明在这里，已经存下来的文章就不会被判为
	   「此区块包含意外或无效的内容」。 */
	deprecated: [
		{
			save: (props) => {
				return (
					<div className={argonBlockClass("alert", props)} {...argonBlockAnchor(props)} style={{ backgroundColor: props.attributes.color }}>
						{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
							<span className="alert-inner--icon">
								<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>
							</span>
						}
						<span className="alert-inner--text" dangerouslySetInnerHTML={{ __html: props.attributes.content }}></span>
					</div>
				);
			},
		},
	],
});
