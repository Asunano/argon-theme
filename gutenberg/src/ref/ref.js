/**
 * argon/ref —— 脚注引用
 *
 * 对应短代码 [ref id="xxx"]脚注内容[/ref]
 *
 * 这是【动态区块】，而且必须是：
 *   脚注的编号（1、2、3…）由服务端在渲染正文时按【出现顺序】累积到全局
 *   $post_references 里，文章底部的脚注列表也由它生成。
 *   如果做成静态区块，编号会被写死在 HTML 里 —— 以后在它前面插一段、
 *   或调整顺序，正文里的上标和底部的列表就对不上了。
 *   用 save: () => null + PHP 的 render_callback，渲染时照常走那套全局累积，
 *   编号与底部列表天然一致。
 *
 * id 属性是可选的：
 *   同一个 id 在多处引用时会复用同一条脚注（上标显示 1、1.1、1.2…）；
 *   留空则按内容去重 —— 与短代码的行为完全一致。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/ref', {
	apiVersion: 2,
	title: __('脚注'),
	icon: 'editor-insertmore',
	category: 'argon',
	keywords: [ 'argon', __('脚注'), __('参考'), 'ref', 'footnote' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		id: {
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
							<div id="gutenpride-controls">
								<fieldset>
									<TextControl
										label={__('引用 ID')}
										value={props.attributes.id}
										onChange={(value) => props.setAttributes({ id: value })}
									/>
									<p className="help-text">
										{__('可选。同一个 ID 在多处引用时会复用同一条脚注（上标显示 1、1.1、1.2…）；留空则按内容去重。')}
									</p>
								</fieldset>
								<fieldset>
									<PanelRow>{__('脚注内容')}</PanelRow>
									<RichText
										tagName="div"
										placeholder={__('脚注内容')}
										value={props.attributes.content}
										onChange={(value) => props.setAttributes({ content: value })}
									/>
									<p className="help-text">
										{__('会显示在文章底部的参考列表里。允许链接、换行、加粗、斜体、上下标。')}
									</p>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="argon/ref"
					attributes={props.attributes}
				/>
			</div>
		);
	},
	/* 动态区块：编号与底部列表都由服务端按渲染顺序生成 */
	save: () => null,
});
