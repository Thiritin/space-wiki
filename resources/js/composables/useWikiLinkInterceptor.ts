import { router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

export function useWikiLinkInterceptor() {
    const handleClick = (event: MouseEvent) => {
        const target = event.target as HTMLElement
        
        // Find the closest anchor tag
        const link = target.closest('a')
        if (!link) return
        
        const href = link.getAttribute('href')
        if (!href) return
        
        // Check if it's a wiki link (internal to our app)
        if (href.startsWith('/wiki/')) {
            // Don't intercept if it's a special key combination (for opening in new tab)
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return
            }
            
            // Don't intercept if it's a right click or middle click
            if (event.button !== 0) {
                return
            }
            
            // Don't intercept if target="_blank" or similar
            const linkTarget = link.getAttribute('target')
            if (linkTarget && linkTarget !== '_self') {
                return
            }
            
            // Don't intercept if link has data-no-inertia attribute
            if (link.hasAttribute('data-no-inertia')) {
                return
            }
            
            // Prevent default browser navigation
            event.preventDefault()
            
            console.log('🔗 Intercepting wiki link:', href)
            
            // Use Inertia router for smooth navigation
            router.visit(href, {
                preserveScroll: false,
                preserveState: false,
            })
        }
    }
    
    // Simple prefetch cache to avoid duplicate requests
    const prefetchCache = new Set<string>()
    
    const handleMouseEnter = (event: MouseEvent) => {
        const target = event.target as HTMLElement
        const link = target.closest('a')
        if (!link) return
        
        const href = link.getAttribute('href')
        if (!href) return
        
        // Prefetch wiki links on hover (only if not already prefetched)
        if (href.startsWith('/wiki/') && !prefetchCache.has(href)) {
            prefetchCache.add(href)
            
            // Add small delay to avoid prefetching on quick mouse movements
            setTimeout(() => {
                console.log('🚀 Prefetching wiki link:', href)
                
                // Simple fetch to warm up the cache
                fetch(href, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Inertia': 'true',
                        'X-Inertia-Version': router.version || '',
                    },
                }).then(() => {
                    console.log('✅ Prefetch completed:', href)
                }).catch((error) => {
                    console.log('❌ Prefetch failed:', href, error)
                    // Remove from cache on error so it can be retried
                    prefetchCache.delete(href)
                })
            }, 100) // 100ms delay
        }
    }
    
    onMounted(() => {
        // Add click interceptor to document
        document.addEventListener('click', handleClick, true)
        
        // Add hover prefetching
        document.addEventListener('mouseenter', handleMouseEnter, true)
    })
    
    onUnmounted(() => {
        document.removeEventListener('click', handleClick, true)
        document.removeEventListener('mouseenter', handleMouseEnter, true)
    })
    
    return {
        // Expose methods if needed for manual use
        interceptWikiLink: (href: string) => {
            if (href.startsWith('/wiki/')) {
                router.visit(href)
            }
        }
    }
}