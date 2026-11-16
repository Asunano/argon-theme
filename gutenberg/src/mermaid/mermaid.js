/**
 * argon/mermaid —— Mermaid 图表（#563）
 *
 * 对应短代码 [mermaid]graph TD; A-->B;[/mermaid]
 *
 * 静态区块：save 输出 <div class="argon-mermaid"><pre>源码</pre></div>，
 * 前端 argonRenderMermaid()（argontheme.js）检测到该结构后按需加载 mermaid.js
 * 并替换为 svg；加载或渲染失败时 <pre> 源码可读，不吞内容。
 * 注意：源码按字面保存，不要在图表源码里写 HTML。
 */

import { __ } from './../i18n/i18n.js';
import { TextareaControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/mermaid', {
	apiVersion: 2,
	title: __('Mermaid 图表'),
	icon: 'editor-code',
	category: 'argon',
	keywords: [ 'argon', 'mermaid', __('图表'), __('流程图') ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		return (
			<div>
				<div className="argon-mermaid" style={{ padding: '12px', borderRadius: '8px', background: '#f5f5f5', fontFamily: 'monospace' }}>
					<TextareaControl
						placeholder={__('图表源码，例如：graph TD; A-->B;')}
						value={props.attributes.content}
						onChange={(value) => props.setAttributes({ content: value })}
						rows={8}
					/>
				</div>
			</div>
		);
	},
	save: (props) => {
		return (
			<div className={argonBlockClass('argon-mermaid', props)} {...argonBlockAnchor(props)}>
				<pre>{props.attributes.content}</pre>
			</div>
		);
	},
});
