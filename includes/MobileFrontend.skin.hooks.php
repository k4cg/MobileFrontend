<?php

use MediaWiki\MediaWikiServices;

class MobileFrontendSkinHooks {
	/**
	 * Make it possible to open sections while JavaScript is still loading.
	 *
	 * @return string The JavaScript code to add event handlers to the skin
	 */
	public static function interimTogglingSupport() {
		$js = <<<JAVASCRIPT
function mfTempOpenSection( id ) {
	var block = document.getElementById( "mf-section-" + id );
	block.className += " open-block";
	// The previous sibling to the content block is guaranteed to be the
	// associated heading due to mobileformatter. We need to add the same
	// class to flip the collapse arrow icon.
	// <h[1-6]>heading</h[1-6]><div id="mf-section-[1-9]+"></div>
	block.previousSibling.className += " open-block";
}
JAVASCRIPT;
		return Html::inlineScript(
			ResourceLoader::filter( 'minify-js', $js )
		);
	}

	/**
	 * Fallback for Grade C to load lazyload image placeholders.
	 *
	 * Note: This will add a single repaint for Grade C browsers as
	 * images enter view but this is intentional and deemed acceptable.
	 *
	 * @return string The JavaScript code to load lazy placeholders in Grade C browsers
	 */
	public static function gradeCImageSupport() {
		// Notes:
		// * Document#getElementsByClassName is supported by IE9+ and #querySelectorAll is
		//   supported by IE8+. To gain the widest possible browser support we scan for
		//   noscript tags using #getElementsByTagName and look at the next sibling.
		//   If the next sibling has the lazy-image-placeholder class then it will be assumed
		//   to be a placeholder and replace with an img tag.
		// * Iterating over the live NodeList from getElementsByTagName() is suboptimal
		//   but in IE < 9, Array#slice() throws when given a NodeList. It also requires
		//   the 2nd argument ('end').
		$js = <<<JAVASCRIPT
(window.NORLQ = window.NORLQ || []).push( function () {
	var ns, i, p, img;
	ns = document.getElementsByTagName( 'noscript' );
	for ( i = 0; i < ns.length; i++ ) {
		p = ns[i].nextSibling;
		if ( p && p.className && p.className.indexOf( 'lazy-image-placeholder' ) > -1 ) {
			img = document.createElement( 'img' );
			img.setAttribute( 'src', p.getAttribute( 'data-src' ) );
			img.setAttribute( 'width', p.getAttribute( 'data-width' ) );
			img.setAttribute( 'height', p.getAttribute( 'data-height' ) );
			img.setAttribute( 'alt', p.getAttribute( 'data-alt' ) );
			p.parentNode.replaceChild( img, p );
		}
	}
} );
JAVASCRIPT;
		return $js;
	}

	/**
	 * Returns HTML of terms of use link or null if it shouldn't be displayed
	 * Note: This is called by a hook in the WikimediaMessages extension.
	 *
	 * @param Skin $sk
	 * @param string $urlMsgKey Key of i18n message containing terms of use URL (optional)
	 * @return null|string
	 */
	public static function getTermsLink( $sk, $urlMsgKey = 'mobile-frontend-terms-url' ) {
		$urlMsg = $sk->msg( $urlMsgKey )->inContentLanguage();
		if ( $urlMsg->isDisabled() ) {
			return null;
		}
		$url = $urlMsg->plain();

		return Html::element(
			'a',
			[ 'href' => Skin::makeInternalOrExternalUrl( $url ) ],
			$sk->msg( 'mobile-frontend-terms-text' )->text()
		);
	}

	/**
	 * Check, if the given license message string holds multiple license messages.
	 *
	 * FIXME: This hack shouldn't be needed anymore after fixing T111833
	 *
	 * @param string $license
	 * @param Message $msgObj delimiter (optional)
	 * @return integer Returns 2, if there are multiple licenses, 1 otherwise.
	 */
	public static function getPluralLicenseInfo( $license, $msgObj = null ) {
		// for plural support we need the info, if there is one or more licenses used in the license text
		// this check if very simple and works on the base, that more than one license will
		// use "and" as a connective
		// 1 - no plural
		// 2 - plural
		if ( $msgObj !== null ) {
			$delimiterMsg = $msgObj;
		} else {
			$delimiterMsg = wfMessage( 'and' );
		}
		$delimiterMsg = $delimiterMsg->inContentLanguage();
		// check, if "and" isn't disabled and exists in site language
		return $delimiterMsg->isDisabled() || strpos( $license, $delimiterMsg->text() ) === false ? 1 : 2;
	}

	/**
	 * Returns HTML of license link or empty string
	 * For example:
	 *   "<a title="Wikipedia:Copyright" href="/index.php/Wikipedia:Copyright">CC BY</a>"
	 *
	 * @param string $context The context in which the license link appears, e.g. footer,
	 *   editor, talk, or upload.
	 * @param array $attribs An associative array of extra HTML attributes to add to the link
	 * @return array Associative array containing the license text and link
	 */
	public static function getLicense( $context, $attribs = [] ) {
		$config = MobileContext::singleton()->getConfig();
		$rightsPage = $config->get( 'RightsPage' );
		$rightsUrl = $config->get( 'RightsUrl' );
		$rightsText = $config->get( 'RightsText' );

		// Construct the link to the licensing terms
		if ( $rightsText ) {
			// Use shorter text for some common licensing strings. See Installer.i18n.php
			// for the currently offered strings. Unfortunately, there is no good way to
			// comprehensively support localized licensing strings since the license (as
			// stored in LocalSettings.php) is just freeform text, not an i18n key.
			$commonLicenses = [
				'Creative Commons Attribution-Share Alike 3.0' => 'CC BY-SA 3.0',
				'Creative Commons Attribution Share Alike' => 'CC BY-SA',
				'Creative Commons Attribution 3.0' => 'CC BY 3.0',
				'Creative Commons Attribution 2.5' => 'CC BY 2.5', // Wikinews
				'Creative Commons Attribution' => 'CC BY',
				'Creative Commons Attribution Non-Commercial Share Alike' => 'CC BY-NC-SA',
				'Creative Commons Zero (Public Domain)' => 'CC0 (Public Domain)',
				'GNU Free Documentation License 1.3 or later' => 'GFDL 1.3 or later',
			];

			if ( isset( $commonLicenses[$rightsText] ) ) {
				$rightsText = $commonLicenses[$rightsText];
			}
			if ( $rightsPage ) {
				$title = Title::newFromText( $rightsPage );
				$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeKnownLink(
					$title,
					new HtmlArmor( $rightsText ),
					$attribs
				);
			} elseif ( $rightsUrl ) {
				$link = Linker::makeExternalLink( $rightsUrl, $rightsText, true, '', $attribs );
			} else {
				$link = $rightsText;
			}
		} else {
			$link = '';
		}

		// Allow other extensions (for example, WikimediaMessages) to override
		$msg = 'mobile-frontend-copyright';
		Hooks::run( 'MobileLicenseLink', [ &$link, $context, $attribs, &$msg ] );

		return [
			'msg' => $msg,
			'link' => $link,
			'plural' => self::getPluralLicenseInfo( $link )
		];
	}

	/**
	 * Prepares the footer for the skins serving the desktop and mobile sites.
	 * @param Skin $skin
	 * @param QuickTemplate $tpl
	 */
	public static function prepareFooter( $skin, $tpl ) {
		$title = $skin->getTitle();
		$req = $skin->getRequest();
		$ctx = MobileContext::singleton();

		// Certain pages might be blacklisted and not have a mobile equivalent.
		if ( !$ctx->isBlacklistedPage() ) {
			if ( $ctx->shouldDisplayMobileView() ) {
				MobileFrontendSkinHooks::mobileFooter( $skin, $tpl, $ctx, $title, $req );
			} else {
				MobileFrontendSkinHooks::desktopFooter( $skin, $tpl, $ctx, $title, $req );
			}
		}
	}

	/**
	 * Appends a mobile view link to the desktop footer
	 * @param Skin $sk
	 * @param QuickTemplate $tpl
	 * @param MobileContext $ctx
	 * @param Title $title
	 * @param WebRequest $req
	 */
	public static function desktopFooter( Skin $sk, QuickTemplate $tpl, MobileContext $ctx,
		Title $title, WebRequest $req
	) {
		$footerlinks = $tpl->data['footerlinks'];
		$args = $req->getQueryValues();
		// avoid title being set twice
		unset( $args['title'], $args['useformat'] );
		$args['mobileaction'] = 'toggle_view_mobile';

		$mobileViewUrl = $title->getFullURL( $args );
		$mobileViewUrl = $ctx->getMobileUrl( $mobileViewUrl );

		$link = Html::element( 'a',
			[ 'href' => $mobileViewUrl, 'class' => 'noprint stopMobileRedirectToggle' ],
			$ctx->msg( 'mobile-frontend-view' )->text()
		);
		$tpl->set( 'mobileview', $link );
		$footerlinks['places'][] = 'mobileview';
		$tpl->set( 'footerlinks', $footerlinks );
	}

	/**
	 * Prepares links used in the mobile footer
	 * @param Skin $sk
	 * @param QuickTemplate $tpl
	 * @param MobileContext $ctx
	 * @param Title $title
	 * @param WebRequest $req
	 * @return QuickTemplate
	 */
	protected static function mobileFooter( Skin $sk, QuickTemplate $tpl, MobileContext $ctx,
		Title $title, WebRequest $req
	) {
		$url = $sk->getOutput()->getProperty( 'desktopUrl' );
		if ( $url ) {
			$url = wfAppendQuery( $url, 'mobileaction=toggle_view_desktop' );
		} else {
			$url = $title->getLocalUrl(
				$req->appendQueryValue( 'mobileaction', 'toggle_view_desktop', true )
			);
		}
		$desktopUrl = $ctx->getDesktopUrl( wfExpandUrl( $url, PROTO_RELATIVE ) );

		$desktop = $ctx->msg( 'mobile-frontend-view-desktop' )->escaped();
		$desktopToggler = Html::element( 'a',
			[ 'id' => "mw-mf-display-toggle", "href" => $desktopUrl ], $desktop );
		$sitename = self::getSitename( true );

		// Generate the licensing text displayed in the footer of each page.
		// See Skin::getCopyright for desktop equivalent.
		$license = self::getLicense( 'footer' );
		if ( isset( $license['link'] ) && $license['link'] ) {
			$licenseText = $sk->msg( $license['msg'] )->rawParams( $license['link'] )->text();
		} else {
			$licenseText = '';
		}

		// Enable extensions to add links to footer in Mobile view, too - bug 66350
		Hooks::run( 'MobileSiteOutputPageBeforeExec', [ &$sk, &$tpl ] );

		$tpl->set( 'footer-site-heading-html', $sitename );
		$tpl->set( 'desktop-toggle', $desktopToggler );
		$tpl->set( 'mobile-license', $licenseText );
		$tpl->set( 'privacy', $sk->footerLink( 'mobile-frontend-privacy-link-text', 'privacypage' ) );
		$tpl->set( 'terms-use', self::getTermsLink( $sk ) );

		$places = [
			'terms-use',
			'privacy',
			'desktop-toggle'
		];
		$footerlinks = [
			'places' => $places,
		];
		$tpl->set( 'footerlinks', $footerlinks );
		return $tpl;
	}

	/**
	 * Returns the site name for the footer, either as a text or <img> tag
	 * @param boolean $withPossibleTrademark If true and a trademark symbol is specified
	 *     by $wgMFTrademarkSitename, append that trademark symbol to the sitename/logo.
	 *     This param exists so that the trademark symbol can be appended in some
	 *     contexts, for example, the footer, but not in others. See bug T95007.
	 * @return string
	 */
	public static function getSitename( $withPossibleTrademark = false ) {
		$ctx = MobileContext::singleton();
		$config = $ctx->getMFConfig();
		$customLogos = $config->get( 'MFCustomLogos' );
		$trademarkSymbol = $config->get( 'MFTrademarkSitename' );
		$suffix = '';

		$footerSitename = $ctx->msg( 'mobile-frontend-footer-sitename' )->text();

		// Add a trademark symbol if needed
		if ( $withPossibleTrademark ) {
			// Registered trademark
			if ( $trademarkSymbol === 'registered' ) {
				$suffix = Html::element( 'sup', [], '®' );
			// Unregistered (or unspecified) trademark
			} elseif ( $trademarkSymbol ) {
				$suffix = Html::element( 'sup', [], '™' );
			}
		}

		// If there's a custom site logo, use that instead of text
		if ( isset( $customLogos['copyright'] ) ) {
			$attributes =  [
				'src' => $customLogos['copyright'],
				'alt' => $footerSitename,
			];
			if ( isset( $customLogos['copyright-height'] ) ) {
				$attributes['height'] = $customLogos['copyright-height'];
			}
			if ( isset( $customLogos['copyright-width'] ) ) {
				$attributes['width'] = $customLogos['copyright-width'];
			}
			$sitename = Html::element( 'img', $attributes );
		} else {
			$sitename = $footerSitename;
		}

		return $sitename . $suffix;
	}
}
