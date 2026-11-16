/**
 * argon/gh-release —— GitHub Release 下载卡
 *
 * 对应短代码 [gh-release repo="Asunano/UFI-AXIS" tag="v0.1.3" mirror="https://mirror.example.com/"]
 *
 * 动态区块：服务端拉 GitHub API 列出 Release 全部附件（每 IP 每小时匿名限流 60 次，
 * 故 transient 缓存 6 小时、失败 30 分钟），渲染与短代码共用 argon_render_gh_release()。
 * 镜像前缀拼接规则：镜像域名 + 完整原始地址（含 https://）。
 * 编辑器画布显示样式化占位卡（附件清单在前台加载），参考 GitHub 信息卡片的模式。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/gh-release', {
	apiVersion: 2,
	title: __('GitHub 下载卡'),
	icon: 'download',
	category: 'argon',
	keywords: [ 'argon', 'github', __('下载'), __('发布'), 'release' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		repo: {
			type: 'string',
			default: '',
		},
		tag: {
			type: 'string',
			default: '',
		},
		mirror: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const a = props.attributes;
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('仓库')}
								help={__('格式 owner/repo，例如 Asunano/UFI-AXIS')}
								value={a.repo}
								onChange={(value) => props.setAttributes({ repo: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标签（可选）')}
								help={__('例如 v0.1.3；留空则显示最新 Release')}
								value={a.tag}
								onChange={(value) => props.setAttributes({ tag: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('镜像前缀（可选）')}
								help={__('加速下载用；所有附件地址会拼接到该域名后，例如 https://mirror.example.com/')}
								value={a.mirror}
								onChange={(value) => props.setAttributes({ mirror: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标题')}
								help={__('留空则显示 Release 名称')}
								value={a.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-gh-release argon-gh-release-editor">
					<div className="argon-gh-release-head">
						<span className="argon-gh-release-title">
							<i className="fa fa-github"></i> {a.title || a.repo || __('仓库（右侧栏填写）')}
						</span>
						<span className="argon-gh-release-tag">{a.tag || 'latest'}</span>
					</div>
					{[1, 2].map((i) => (
						<div key={i} className="argon-gh-release-item argon-gh-release-skeleton">
							<span className="argon-gh-release-name"><span className="argon-editor-skeleton-bar" style={{ width: '120px', display: 'inline-block' }}></span></span>
							<span className="argon-gh-release-size"><span className="argon-editor-skeleton-bar" style={{ width: '40px', display: 'inline-block' }}></span></span>
							<span className="argon-gh-release-btn"><i className="fa fa-download"></i> {__('下载')}</span>
						</div>
					))}
					<div className="argon-gh-release-editor-note">
						{a.mirror ? __('附件经镜像加速下载 · ') : ''}
						{__('附件清单在前台自动加载（缓存 6 小时）')}
					</div>
				</div>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
