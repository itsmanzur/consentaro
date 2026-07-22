# Build a WordPress.org-ready ZIP as consentflow-1.0.0.zip
# Usage: powershell -File bin/build-release.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$version = "1.0.0"
$slug = "consentflow"
$distIgnorePath = Join-Path $root ".distignore"
$stagingParent = Join-Path $env:TEMP "consentflow-release-staging"
$staging = Join-Path $stagingParent $slug
$outZip = Join-Path $root "dist\$slug-$version.zip"

Write-Host "Building admin assets..."
npm run build

if (Test-Path $stagingParent) {
	Remove-Item -Recurse -Force $stagingParent
}
New-Item -ItemType Directory -Path $staging -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $root "dist") -Force | Out-Null

$patterns = @()
if (Test-Path $distIgnorePath) {
	$patterns = Get-Content $distIgnorePath |
		ForEach-Object { $_.Trim() } |
		Where-Object { $_ -ne "" -and -not $_.StartsWith("#") }
}

function Should-Exclude([string]$relativePath) {
	$norm = $relativePath.Replace("\", "/")
	foreach ($p in $patterns) {
		$pat = $p.Replace("\", "/").TrimEnd("/")
		if ($pat.StartsWith("!")) { continue }
		if ($norm -eq $pat) { return $true }
		if ($norm.StartsWith("$pat/")) { return $true }
		# simple glob: *.pdf
		if ($pat.StartsWith("*.") -and $norm.EndsWith($pat.Substring(1))) { return $true }
	}
	return $false
}

Write-Host "Copying files to staging ($slug/)..."
Get-ChildItem -Path $root -Recurse -Force | Where-Object {
	-not $_.PSIsContainer
} | ForEach-Object {
	$rel = $_.FullName.Substring($root.Length).TrimStart("\", "/")
	if ($rel.StartsWith("dist\") -or $rel.StartsWith("dist/")) { return }
	if ($rel.StartsWith(".git\") -or $rel.StartsWith(".git/")) { return }
	if (Should-Exclude $rel) { return }

	$dest = Join-Path $staging $rel
	$destDir = Split-Path -Parent $dest
	if (-not (Test-Path $destDir)) {
		New-Item -ItemType Directory -Path $destDir -Force | Out-Null
	}
	Copy-Item -LiteralPath $_.FullName -Destination $dest -Force
}

# Required built files check
$required = @(
	"assets\build\admin.js",
	"assets\js\banner.js",
	"assets\js\gtm-loader.js",
	"assets\js\woo.js",
	"assets\css\banner.css",
	"consentflow.php",
	"readme.txt",
	"uninstall.php"
)
foreach ($r in $required) {
	$check = Join-Path $staging $r
	if (-not (Test-Path $check)) {
		throw "Missing required file in package: $r"
	}
}

if (Test-Path $outZip) {
	Remove-Item -Force $outZip
}

Write-Host "Creating $outZip ..."
Compress-Archive -Path $staging -DestinationPath $outZip -Force

$size = (Get-Item $outZip).Length
Write-Host ("Done: {0} ({1:N0} bytes)" -f $outZip, $size)

Remove-Item -Recurse -Force $stagingParent
