/**
 * argon/gate —— 内容门控（回复可见 / 登录可见）
 *
 * 对应短代码 [reply]…[/reply] 与 [login]…[/login]（上游 #505 not_planned，本分支接手）
 *
 * 动态区块 + InnerBlocks：
 *   · save 只输出 <InnerBlocks.Content />，内层内容照常存进文章；
 *   · 前台由 PHP 的 render_callback 决定输出——未授权时直接把 $content 丢掉，
 *     所以被保护内容根本不会出现在 HTML 里（不是用 CSS 藏起来）。
 * 编辑器里始终显示内层内容，方便编辑；权限判断只发生在前台。
 */

import { __ } from './../i18n/i18n.js';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

const TEMPLATE = [['core/paragraph', { placeholder: __('这里的文字只有满足条件的访客能看到…') }]];

registerBlockType('argon/gate', {
	apiVersion: 2,
	title: __('内容门控'),
	icon: 'lock',
	category: 'argon',
	keywords: [ 'argon', __('回复可见'), __('登录可见'), 'reply', 'login', 'gate' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		type: {
			type: 'string',
			default: 'reply',
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const isLogin = props.attributes.type === 'login';
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('可见条件')}
									value={props.attributes.type}
									options={[
										{ label: __('回复后可见'), value: 'reply' },
										{ label: __('登录后可见'), value: 'login' },
									]}
									onChange={(value) => props.setAttributes({ type: value })}
								/>
							</fieldset>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div
					style={{
						borderLeft: '3px solid #5e72e4',
						padding: '12px 16px',
						borderRadius: '8px',
						background: '#f7f7fb',
					}}
				>
					<div style={{ fontSize: '12px', opacity: 0.7, marginBottom: '6px' }}>
						<i className="fa fa-lock"></i>{' '}
						{isLogin ? __('登录后可见（编辑器里始终显示）') : __('回复后可见（编辑器里始终显示）')}
					</div>
					<InnerBlocks template={TEMPLATE} templateInsertUpdatesSelection={false} />
				</div>
			</div>
		);
	},
	save: () => <InnerBlocks.Content />,
});
