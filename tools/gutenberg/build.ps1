# 构建古腾堡区块产物
#
# 背景：2026-10 之前用的是 cgb-scripts@1.23.1（2017 年的 webpack3 + node-sass），
# 本地和 CI 都装不上（node-sass 需要 Python 2.7 做原生编译），CI 那一步又写着
# continue-on-error: true，于是构建静默失败、步骤显示绿色，
# 发布包里打的一直是仓库里 2020 年那次构建的旧产物。
#
# 现在换成 @wordpress/scripts（webpack5 + dart-sass，纯 JS 无原生编译），
# 任何 Node 版本都能跑。产物提交在 gutenberg/dist/ 里。
#
# 用法：
#   pwsh tools/gutenberg/build.ps1            # 安装依赖（如有需要）并构建
#   pwsh tools/gutenberg/build.ps1 -SkipInstall

param(
	[switch]$SkipInstall
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$dir = Join-Path $repo 'gutenberg'

if (-not (Test-Path $dir)) {
	throw "找不到 gutenberg 目录: $dir"
}

Push-Location $dir
try {
	if (-not $SkipInstall) {
		if (Test-Path 'package-lock.json') {
			Write-Host '==> npm ci' -ForegroundColor Cyan
			npm ci
		} else {
			Write-Host '==> npm install（没有 package-lock.json，建议提交它以保证构建可复现）' -ForegroundColor Yellow
			npm install
		}
		if ($LASTEXITCODE -ne 0) { throw '依赖安装失败' }
	}

	Write-Host '==> npm run build' -ForegroundColor Cyan
	npm run build
	if ($LASTEXITCODE -ne 0) { throw '构建失败' }

	Write-Host ''
	Write-Host '==> 产物：' -ForegroundColor Cyan
	Get-ChildItem 'dist' | ForEach-Object {
		'{0,-24} {1,8:N1} KB' -f $_.Name, ($_.Length / 1KB)
	}

	Write-Host ''
	Write-Host '==> 别忘了提交 gutenberg/dist/' -ForegroundColor Yellow
	$dirty = git status --porcelain dist
	if ($dirty) {
		Write-Host '    以下产物有改动：' -ForegroundColor Yellow
		$dirty | ForEach-Object { Write-Host "      $_" }
	} else {
		Write-Host '    产物与仓库一致' -ForegroundColor Green
	}
} finally {
	Pop-Location
}
