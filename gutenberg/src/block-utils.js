/**
 * 区块共用的工具。
 *
 * 为什么不用 useBlockProps.save()：
 *   它会把 wp-block-<namespace>-<name> 这个类也一并带上，而 WordPress 本身
 *   在保存时已经会自动加它 —— 两处都加就会重复。
 *   这里手工拼 class，好处是【在没设自定义类、没选对齐时，产物与旧版完全一致】，
 *   老文章不会因为这次改动而失效（区块失效判定是逐字节比对 save 的输出）。
 *
 * 用法（在 save 里）：
 *   <div className={argonBlockClass('alert', props)} {...argonBlockAnchor(props)}>
 */

/**
 * 拼出根元素的 className。
 *
 * 【重要】没有任何类名时返回 undefined —— React 不会为 undefined 输出 class 属性。
 * 这一点是「不破坏老文章」的关键：像 timeline 那种根元素是裸 <div> 的区块，
 * 如果返回空串，产物会从 <div> 变成 <div class="">，与旧产物不一致，
 * WordPress 会判定「此区块包含意外或无效的内容」。
 *
 * @param {string} base  区块原有的类名（可以是空串）
 * @param {Object} props save/edit 收到的 props
 * @return {string|undefined}
 */
export function argonBlockClass(base, props) {
	const extra = [];

	// 对齐：WP 的约定是 alignwide / alignfull / alignleft / aligncenter / alignright
	const align = props && props.attributes ? props.attributes.align : undefined;
	if (align) {
		extra.push('align' + align);
	}

	// 编辑器侧边栏「额外的 CSS 类」——WP 通过 props.className 传进来
	if (props && props.className) {
		extra.push(props.className);
	}

	const cls = [base, ...extra].filter(Boolean).join(' ').trim();
	return cls === '' ? undefined : cls;
}

/**
 * 锚点：supports.anchor 打开后，WP 通过 props.anchor 传 id。
 * 没设时返回空对象，不会产生额外属性 —— 保证旧产物不变。
 *
 * @param {Object} props
 * @return {Object}
 */
export function argonBlockAnchor(props) {
	return props && props.anchor ? { id: props.anchor } : {};
}
