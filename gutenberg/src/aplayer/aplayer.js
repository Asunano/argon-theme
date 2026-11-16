/**
 * argon/aplayer —— 音频播放器（APlayer）
 *
 * 对应短代码：
 *   单曲  [aplayer src="a.mp3" name="曲名" artist="歌手" cover="封面" lrc="歌词"]
 *   列表  [aplayer]曲名 | a.mp3 | 歌手 | 封面 | 歌词[/aplayer]（每行一条）
 *
 * 动态区块：后台开关（argon_enable_media_player）决定前台走 APlayer 还是原生 <audio> 降级，
 * 该判断只应有一份，故与短代码共用 argon_render_aplayer()。
 * 播放器库本身由前端按需加载，编辑器里看到的是服务端渲染的占位/降级形态。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const { registerBlockType } = wp.blocks;

const emptyTrack = () => ({ name: '', artist: '', url: '', cover: '', lrc: '' });

registerBlockType('argon/aplayer', {
	apiVersion: 2,
	title: __('音频播放器'),
	icon: 'format-audio',
	category: 'argon',
	keywords: [ 'argon', 'aplayer', __('音频'), __('音乐') ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		tracks: {
			type: 'array',
			default: [],
		},
		theme: {
			type: 'string',
			default: '',
		},
		mode: {
			type: 'string',
			default: 'normal',
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
		random: {
			type: 'boolean',
			default: false,
		},
		volume: {
			type: 'string',
			default: '',
		},
		list_folded: {
			type: 'boolean',
			default: false,
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const tracks = props.attributes.tracks || [];
		const updateTrack = (index, key, value) => {
			const next = tracks.map((item, i) => (i === index ? { ...item, [key]: value } : item));
			props.setAttributes({ tracks: next });
		};
		const removeTrack = (index) => {
			props.setAttributes({ tracks: tracks.filter((item, i) => i !== index) });
		};
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('曲目')} icon="playlist-audio" initialOpen={true}>
						<PanelRow>
							<div style={{ width: '100%' }}>
								{tracks.map((track, index) => (
									<div
										key={index}
										style={{
											borderBottom: '1px solid #e0e0e0',
											paddingBottom: '10px',
											marginBottom: '10px',
										}}
									>
										<TextControl
											label={__('音频地址')}
											value={track.url}
											onChange={(value) => updateTrack(index, 'url', value)}
										/>
										<TextControl
											label={__('曲名')}
											value={track.name}
											onChange={(value) => updateTrack(index, 'name', value)}
										/>
										<TextControl
											label={__('艺术家')}
											value={track.artist}
											onChange={(value) => updateTrack(index, 'artist', value)}
										/>
										<TextControl
											label={__('封面')}
											value={track.cover}
											onChange={(value) => updateTrack(index, 'cover', value)}
										/>
										<TextControl
											label={__('歌词地址')}
											value={track.lrc}
											onChange={(value) => updateTrack(index, 'lrc', value)}
										/>
										<Button isDestructive isSmall onClick={() => removeTrack(index)}>
											{__('移除此项')}
										</Button>
									</div>
								))}
								<Button
									isSecondary
									onClick={() => props.setAttributes({ tracks: [...tracks, emptyTrack()] })}
								>
									{__('+ 添加曲目')}
								</Button>
							</div>
						</PanelRow>
					</PanelBody>
					<PanelBody title={__('区块设置')} icon="more" initialOpen={false}>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('模式')}
									value={props.attributes.mode}
									options={[
										{ label: __('普通'), value: 'normal' },
										{ label: __('迷你'), value: 'mini' },
										{ label: __('吸底'), value: 'fixed' },
									]}
									onChange={(value) => props.setAttributes({ mode: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('主题色（留空跟随主题）')}
								value={props.attributes.theme}
								onChange={(value) => props.setAttributes({ theme: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('自动播放')}
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
								label={__('随机播放')}
								checked={props.attributes.random}
								onChange={(value) => props.setAttributes({ random: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('默认折叠播放列表')}
								checked={props.attributes.list_folded}
								onChange={(value) => props.setAttributes({ list_folded: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="aplayer-editor-mock">
					<div className="aplayer-editor-mock-head">
						<span className="aplayer-editor-mock-btn"><i className="fa fa-play"></i></span>
						<span className="aplayer-editor-mock-title">
							{tracks.length > 0
								? `${tracks[0].name || tracks[0].url || __('未命名音频')}${tracks.length > 1 ? ` 等 ${tracks.length} 首` : ''}`
								: __('音频播放器')}
						</span>
					</div>
					{tracks.length > 0
						? <div className="aplayer-editor-mock-list">{tracks.map((t, i) => <div key={i} className="aplayer-editor-track">{t.name || t.url || __('未命名音频')}</div>)}</div>
						: <div className="aplayer-editor-mock-hint">{__('在右侧「曲目」中添加音频后，这里会显示曲目列表')}</div>}
					<div className="aplayer-editor-mock-note">
						{__('播放器在前台加载渲染')}
						{props.attributes.mode === 'fixed' ? ' · ' + __('吸底') : props.attributes.mode === 'mini' ? ' · ' + __('迷你') : ''}
					</div>
				</div>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
