import './editor.scss';
import { argonBlockClass, argonBlockAnchor, argonColorClass, argonColorStyle } from './../block-utils.js';
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
	ColorPalette,
	TextControl,
	ToggleControl,
	Panel, PanelBody, PanelRow
} from '@wordpress/components';
import { isWhitespaceCharacter } from 'is-whitespace-character'

const { registerBlockType } = wp.blocks;

registerBlockType('argon/admonition', {
	apiVersion: 2,
	title: __('警告'),
	icon: 'text-page',
	category: 'argon',
	supports: {
		align: ['wide', 'full'],
		anchor: true,
		className: true,
	},
	keywords: [
		'argon',
		__('警告')
	],
	attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		title: {
			type: 'string',
			default: ''
		},
		content: {
			type: 'string',
			default: ''
		},
		fa_icon_name: {
			type: 'string',
			default: ''
		},
		show_title: {
			type: 'bool',
			default: false
		},
		show_content: {
			type: 'bool',
			default: true
		},
	},
	edit: (props) => {
		const onChangeTitle = (value) => {
			props.setAttributes({ title: value });
		};
		const onChangeContent = (value) => {
			props.setAttributes({ content: value });
		};
		const onChangeColor = (value) => {
			props.setAttributes({ color: (value || "#7889e8") });
		}
		const onIconChange = (value) => {
			props.setAttributes({ fa_icon_name: value });
		}
		const onTitleStatusChange = (value) => {
			props.setAttributes({ show_title: value });
			if (!value) {
				props.setAttributes({ show_content: true });
			}
 		}
		const onContentStatusChange = (value) => {
			props.setAttributes({ show_content: value });
			if (!value) {
				props.setAttributes({ show_title: true });
			}
 		}

		return (
			<div>
				<div className={argonBlockClass("admonition shadow-sm", props)} {...argonBlockAnchor(props)} style={{ borderLeftColor: props.attributes.color }}>
					{props.attributes.show_title &&
						<div className="admonition-title" style={{ backgroundColor: props.attributes.color + "33" }}>
							{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
								<span>
									<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>&nbsp;
								</span>
							}
							<RichText
								tagName="span"
								placeholder={__("标题")}
								value={props.attributes.title}
								onChange={onChangeTitle}
							/>
						</div>
					}
					{props.attributes.show_content &&
						<RichText
							tagName="div"
							className="admonition-body"
							placeholder={__("内容")}
							value={props.attributes.content}
							onChange={onChangeContent}
						/>
					}
				</div>
				<InspectorControls key="setting">
					<PanelBody title={__("区块设置")} icon={"more"} initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<PanelRow>{__('颜色', 'argon')}</PanelRow>
									<ColorPalette
										onChange={onChangeColor}
										colors={[
											{ name: 'argon', color: '#7889e8' },
											{ name: 'green', color: '#4fd69c' },
											{ name: 'red', color: '#f75676' },
											{ name: 'blue', color: '#37d5f2' },
											{ name: 'orange', color: '#fc7c5f' },
											{ name: 'pink', color: '#fa7298' },
											{ name: 'black', color: '#3c4d69' },
											{ name: 'grey', color: '#888888' },
										]}
										value={props.attributes.color}
									/>
								</fieldset>
								<fieldset>
									<PanelRow>{__('部件', 'argon')}</PanelRow>
									<ToggleControl
										label={__('标题', 'argon')}
										checked={props.attributes.show_title}
										onChange={onTitleStatusChange}
									/>
									<ToggleControl
										label={__('内容', 'argon')}
										checked={props.attributes.show_content}
										onChange={onContentStatusChange}
									/>
								</fieldset>
								{props.attributes.show_title &&
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
								}
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		/* 颜色改走类名。短代码用的是 admonition-<name>，区块原来写行内样式，
		   行内样式优先级最高，主题定制与暗色模式都盖不住。
		   预设色映射成 admonition-<name>，根元素与标题都不再写行内样式
		   （标题背景由 CSS 的 .admonition-<name> > .admonition-title 提供）；
		   用户自定义色映射不到，仍然保留行内样式，否则颜色会丢。 */
		const colorClass = argonColorClass('admonition', props.attributes.color);
		const isPreset = colorClass !== '';
		return (
			<div className={argonBlockClass(`admonition shadow-sm ${colorClass}`.trim(), props)} {...argonBlockAnchor(props)} style={isPreset ? undefined : { borderLeftColor: props.attributes.color }}>
				{props.attributes.show_title &&
					<div className="admonition-title" style={isPreset ? undefined : { backgroundColor: props.attributes.color + "33" }}>
						{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
							<span>
								<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>&nbsp;
							</span>
						}
						<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
					</div>
				}
				{props.attributes.show_content &&
					<div className="admonition-body" dangerouslySetInnerHTML={{ __html: props.attributes.content }}/>
				}
			</div>
		);
	},
	/* 旧版 markup 声明：阶段 3 之前颜色是写死在行内 style 里的，没有 admonition-<name> 类。
	   原样声明在这里，已存文章就不会被判为「此区块包含意外或无效的内容」。 */
	deprecated: [
		{
			save: (props) => {
				return (
					<div className={argonBlockClass("admonition shadow-sm", props)} {...argonBlockAnchor(props)} style={{ borderLeftColor: props.attributes.color }}>
						{props.attributes.show_title &&
							<div className="admonition-title" style={{ backgroundColor: props.attributes.color + "33" }}>
								{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
									<span>
										<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>&nbsp;
									</span>
								}
								<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
							</div>
						}
						{props.attributes.show_content &&
							<div className="admonition-body" dangerouslySetInnerHTML={{ __html: props.attributes.content }}/>
						}
					</div>
				);
			},
		},
	],
});