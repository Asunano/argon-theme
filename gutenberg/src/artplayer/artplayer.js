/**
 * argon/artplayer —— 视频播放器（ArtPlayer）
 *
 * 对应短代码 [artplayer src="v.mp4" poster="封面" title="标题" autoplay="true"]
 *
 * 动态区块：后台开关（argon_enable_media_player）决定前台走 ArtPlayer 还是原生 <video> 降级，
 * 与短代码共用 argon_render_artplayer()。播放器库由前端按需加载。
 * 注意：autoplay 在浏览器里必须有 muted 兜底，PHP 侧已强制处理（未显式 muted 时自动静音）。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/artplayer', {
	apiVersion: 2,
	title: __('视频播放器'),
	icon: 'video-alt2',
	category: 'argon',
	keywords: [ 'argon', 'artplayer', __('视频'), __('播放器') ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		src: {
			type: 'string',
			default: '',
		},
		poster: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: '',
		},
		autoplay: {
			type: 'boolean',
			default: false,
		},
		muted: {
			type: 'boolean',
			default: false,
		},
		loop: {
			type: 'boolean',
			default: false,
		},
		hotkey: {
			type: 'boolean',
			default: true,
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
								label={__('视频地址')}
								help={__('支持 mp4 等浏览器可直接播放的格式')}
								value={props.attributes.src}
								onChange={(value) => props.setAttributes({ src: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('封面')}
								value={props.attributes.poster}
								onChange={(value) => props.setAttributes({ poster: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标题')}
								value={props.attributes.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('自动播放')}
								help={__('浏览器要求自动播放必须静音，未开启静音时会自动静音')}
								checked={props.attributes.autoplay}
								onChange={(value) => props.setAttributes({ autoplay: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('默认静音')}
								checked={props.attributes.muted}
								onChange={(value) => props.setAttributes({ muted: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('循环播放')}
								checked={props.attributes.loop}
								onChange={(value) => props.setAttributes({ loop: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('启用快捷键')}
								checked={props.attributes.hotkey}
								onChange={(value) => props.setAttributes({ hotkey: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/artplayer" attributes={props.attributes} />
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
