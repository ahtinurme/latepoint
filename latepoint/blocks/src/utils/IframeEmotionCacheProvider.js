import { useState } from '@wordpress/element';
import { useRefEffect } from '@wordpress/compose';
import createCache from '@emotion/cache';
import { CacheProvider } from '@emotion/react';

// WordPress 6.9+ renders blocks inside an iframe when every registered block
// declares apiVersion >= 3. Emotion's default cache injects <style> tags into
// the parent document's <head>, so styled components rendered inside the iframe
// appear unstyled. This provider builds an Emotion cache whose container is
// the iframe document's <head> and wraps children in <CacheProvider> so all
// styled components within it land their CSS in the correct document.
export default function IframeEmotionCacheProvider( { children } ) {
	const [ cache, setCache ] = useState( null );

	const ref = useRefEffect( ( element ) => {
		const doc = element.ownerDocument;
		setCache(
			createCache( {
				key: 'latepoint-block',
				container: doc.head,
			} )
		);
	}, [] );

	return (
		<div ref={ ref } className="latepoint-block-iframe-cache-host">
			{ cache && <CacheProvider value={ cache }>{ children }</CacheProvider> }
		</div>
	);
}
