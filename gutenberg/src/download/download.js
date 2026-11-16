/**
 * argon/download —— 网盘下载卡片（#234）
 *
 * 对应短代码 [download type="baidu" name="xxx.zip" size="12.5 MB" url="https://..." pwd="abcd"]说明[/download]
 *
 * 动态区块：卡片 HTML 由 PHP 的 shortcode_download() 生成（与短代码共用同一份渲染），
 * save 返回 null，编辑器用 ServerSideRender 预览真实结果。
 * 类型清单（图标/徽章色/文案）维护在 PHP 侧的 $types 表里，这里只做透传。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextareaControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/download', {
	apiVersion: 2,
	title: __('下载卡片'),
	icon: 'download',
	category: 'argon',
	keywords: [ 'argon', __('下载'), 'download', 'file' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		type: {
			type: 'string',
			default: 'other',
		},
		name: {
			type: 'string',
			default: '',
		},
		size: {
			type: 'string',
			default: '',
		},
		version: {
			type: 'string',
			default: '',
		},
		date: {
			type: 'string',
			default: '',
		},
		pwd: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: '',
		},
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('类型')}
									value={props.attributes.type}
									options={[
										{ label: __('网盘下载'), value: 'other' },
										{ label: __('直链下载'), value: 'direct' },
										{ label: __('蓝奏云'), value: 'lanzou' },
										{ label: __('百度网盘'), value: 'baidu' },
										{ label: __('夸克网盘'), value: 'quark' },
										{ label: __('123云盘'), value: '123' },
										{ label: __('阿里云盘'), value: 'ali' },
										{ label: __('腾讯微云'), value: 'weiyun' },
										{ label: __('OneDrive'), value: 'onedrive' },
										{ label: __('Google Drive'), value: 'google' },
									]}
									onChange={(value) => props.setAttributes({ type: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('下载链接')}
								value={props.attributes.url}
								onChange={(value) => props.setAttributes({ url: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('文件名')}
								value={props.attributes.name}
								onChange={(value) => props.setAttributes({ name: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('大小')}
								value={props.attributes.size}
								onChange={(value) => props.setAttributes({ size: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('版本')}
								value={props.attributes.version}
								onChange={(value) => props.setAttributes({ version: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('更新时间')}
								value={props.attributes.date}
								onChange={(value) => props.setAttributes({ date: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('提取码')}
								value={props.attributes.pwd}
								onChange={(value) => props.setAttributes({ pwd: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('图标')}
								help={__('Font Awesome 中的图标名，留空则按类型显示')}
								value={props.attributes.icon}
								onChange={(value) => props.setAttributes({ icon: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextareaControl
								label={__('说明')}
								value={props.attributes.content}
								onChange={(value) => props.setAttributes({ content: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="argon/download"
					attributes={props.attributes}
				/>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
