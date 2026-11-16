/**
 * argon/netease —— 网易云音乐外链播放器
 *
 * 对应短代码 [netease]1868553[/netease] 或 [netease id="..." type="playlist"]
 *
 * 动态区块：用网易云官方外链 iframe（outchain player）渲染，与短代码共用
 * argon_render_netease()。官方外链播放器只支持单曲与歌单两种形态。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/netease', {
	apiVersion: 2,
	title: __('网易云音乐'),
	icon: 'format-audio',
	category: 'argon',
	keywords: [ 'argon', 'netease', __('网易云'), __('音乐') ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		input: {
			type: 'string',
			default: '',
		},
		type: {
			type: 'string',
			default: '',
		},
		height: {
			type: 'integer',
			default: 0,
		},
		autoplay: {
			type: 'boolean',
			default: false,
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('歌曲 ID / 歌单 ID / 链接')}
								value={props.attributes.input}
								onChange={(value) => props.setAttributes({ input: value })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('类型')}
									value={props.attributes.type}
									options={[
										{ label: __('自动识别'), value: '' },
										{ label: __('单曲'), value: 'song' },
										{ label: __('歌单'), value: 'playlist' },
									]}
									onChange={(value) => props.setAttributes({ type: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								type="number"
								label={__('高度 px（可选）')}
								value={props.attributes.height}
								onChange={(value) => props.setAttributes({ height: parseInt(value || 0) })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('自动播放')}
								checked={props.attributes.autoplay}
								onChange={(value) => props.setAttributes({ autoplay: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="argon/netease"
					attributes={props.attributes}
				/>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
