{**
 * templates/ritNod/agreement.tpl
 *
 * Copyright (c) 2023 Sasz Kolomon
 *
 * Aссession Agreement template
 *}

{* {include file="frontend/components/header.tpl"} *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{if !$pageTitleTranslated}{capture assign="pageTitleTranslated"}{translate key='dogovir.title'}{/capture}{/if}
{include file="frontend/components/headerHead.tpl"}

<header class="pkp_structure_head" id="headerNavigationContainer" role="banner">
	<div class="pkp_head_wrapper">
		<div class="pkp_site_name_wrapper agr_logo">
			<div class="pkp_site_name">
				{capture assign="homeUrl"}
					{url page="index" router=\PKP\core\PKPApplication::ROUTE_PAGE}
				{/capture}
				{if $displayPageHeaderLogo}
					<a href="{$homeUrl}" class="is_img">
						<img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}"
							width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}"
							{if $displayPageHeaderLogo.altText != ''}alt="{$displayPageHeaderLogo.altText|escape}" {/if} />
					</a>
				{elseif $displayPageHeaderTitle}
					<a href="{$homeUrl}" class="is_text">{$displayPageHeaderTitle|escape}</a>
				{else}
					<a href="{$homeUrl}" class="is_img">
						<img src="{$baseUrl}/templates/images/structure/logo.png" alt="{$applicationName|escape}"
							title="{$applicationName|escape}" width="180" height="90" />
					</a>
				{/if}
			</div>
		</div>
	</div>
</header>

<style>
	body {
		font-family: sans-serif;
		/* height: 100vh; */
	}
	h1.cmp_welcome {
		margin-block: 2rem!important;
		font-size: 1.75rem;
	}
	.container h2 {
		font-size: 1.3rem;
	}
	.container {
		max-width: 800px;
		margin-inline: auto;
		height: 100%;
		padding: 0.75rem 1rem 1.5rem;
		position: relative;
	}
	.agreement {		
		padding: 1rem 2rem;
		font-family: serif;
		font-size: 1.2rem;
		line-height: 1.5;
		border-radius: 5px;
		border: 1px solid #999;
		resize: none;
		overflow-y: scroll;
		outline: none;
		flex: 1;
	}
	.actions {
		display: flex;
		justify-content: flex-end;
		flex-wrap: wrap;
		margin: 1.5rem 1.5rem 0;
		padding-bottom: 1rem;
		gap: 1rem;
		position: sticky;
		bottom: -8rem;
		transition:all 0.5s ease-in;
		transition-delay: 0.5s;
	}
	.btn {
		display: inline-block;
		text-decoration: none;
		padding: 0.5rem 1rem;
		border: 1px solid;
		border-radius: 4px;
		font-size: 1.25rem;
		cursor: pointer;
	}
	.agree_btn {
		color: white;
		background-color: green;
	}
	.decline_btn {
		color: #d00a0a;
		background-color: white;
	}
	.agree_btn:hover, .agree_btn:focus-visible {
		background-color: #00ab00;
		outline-color: green;
	}
	.decline_btn:hover, .decline_btn:focus-visible {
		color: red;
		outline-color: red;
	}
	.agree_notes {
		margin-block: 0.5rem;
	}
	.pkp_structure_sidebar.left {
		position: absolute;
		right: 0.75rem;
		top: 0;
		width: unset;
	}
	.block_language {
		padding: 0;
	}
	.block_language .title {
		display: none;
	}
	.content ul {
		display: flex;
		flex-direction: row-reverse;
	}
	.locale_en a, .locale_uk a {
		display: inline-block;
		width: 3rem;
		overflow: hidden;
		white-space: nowrap;
		height: 100%;
	}
	.locale_en a::before {
		content: 'ENG';
		width: 3rem;
		display: inline-block;
	}
	.locale_uk a::before {
		content: 'УКР';
		width: 3rem;
		display: inline-block;
	}
	.current a::before {
		text-decoration: underline;
		text-underline-offset: 3px;
		text-decoration-thickness: 2px;
		font-weight: bold;
	}
	.pkp_structure_footer {
		pointer-events: none;
	}
	@media (min-width: 768px) {
		.agr_logo {
			position: relative;
			top: -2.1rem;
			margin-bottom: -1rem;
		}
	}
	.pkp_head_wrapper {
		pointer-events: none;
	}
	.wrapper {
		display: flex;
	}
	.wrapper::before {
		content: '';
		flex:1;
	}
	.wrapper::after {
		content: '';
		flex:3;
	}
	.descr1, .descr2 {
		font-size: 1.15rem;
		line-height: 1.5;
	}
	.agreement h1, .agreement h2 {
		text-align: center;
	}
</style>

<body>
<div class="wrapper">
	<div class="container">
		{capture assign="sidebarCode"}{call_hook name="Templates::Common::Sidebar"}{/capture}
		{if $sidebarCode}
			<div class="pkp_structure_sidebar left" role="complementary">
				{$sidebarCode}
			</div>
		{/if}

		<h1 class="cmp_welcome">
			{translate key='dogovir.title'}
		</h1>
		<h2>
			{translate key='dogovir.subtitle'}
		</h2>
		<p class="descr1">
			{translate key='dogovir.description'}
		</p>

		<div class="agreement">
			{$agreementText}
		</div>
		<p class="descr2">
			{translate key='dogovir.description2'}
		</p>
		<div id="actions" class="actions">
			<button class="btn agree_btn" onclick="ask()">
				{translate key='dogovir.sign'}
			</button> 
			<button class="btn decline_btn" onclick="location.replace('login/signOut')">
				{translate key='dogovir.decline'}
			</button>				
		</div>
		<div class="agree_notes">
			{translate key='dogovir.notes'}
		</div>
		
		{* {include file="frontend/components/footer.tpl"} *}
	</div>
</div>

<div class="pkp_structure_footer_wrapper" role="contentinfo">
	<div class="pkp_structure_footer">
		{if $pageFooter || ($activeTheme->getOption('displayPageFooterLogo')!=='none' && $displayPageHeaderLogo)}
			<div class="pkp_footer_content">
				<a href="{url page="about"}">
					{if $activeTheme->getOption('displayPageFooterLogo')!=='none' && $displayPageHeaderLogo}
						<p class="footer-logo{if $activeTheme->getOption('displayPageFooterLogo')==='mono'} mono-logo{/if}">
							<img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}"
								width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}"
								{if $displayPageHeaderLogo.altText != ''}alt="{$displayPageHeaderLogo.altText|escape}" {/if} />
						</p>
					{/if}
					{if $pageFooter}
						{$pageFooter}
					{/if}
				</a>
			</div>
		{/if}

		<div class="pkp_brand_footer">
			<a href="{url page="about" op="aboutThisPublishingSystem"}">
				<img alt="{translate key="about.aboutThisPublishingSystem"}" src="{$baseUrl}/{$brandImage}">
			</a>
		</div>
	</div>
</div>
<script>
	window.onload=()=>{ let act = document.getElementById('actions');act.style.bottom = '0';};
	function getKey(name) {
		const value = `; ${ document.cookie}`;
		const parts = value.split(`; ${ name}=`);
		if (parts.length === 2) return parts.pop().split(';').shift();
	}
	function ask() {
		let k = getKey('dogovir');
		if(!k) location.reload();
		else if(confirm("{translate key='dogovir.ask' name=$name name_en=$name_en}")) {
			location.replace('index?' + k);
		}
	};
</script>
<style>
	h1, h2 {
		all: revert;
		line-height: 2.143rem;
	}
	.agreement, .agreement * {
		font-size: 1.25rem!important;
		line-height: 1.5;
		text-align: justify;
	}
	.agreement h1, .agreement h1 * {
		font-size: 1.5rem!important;
	}
	.agreement h2, .agreement h2 *{
		font-size: 1.4rem;
	}
	.agreement .MsoFootnoteText, .agreement .MsoFootnoteText * {
		font-size: 1rem!important;
		line-height: 1.25;
	}
</style>
</body>
</html>
