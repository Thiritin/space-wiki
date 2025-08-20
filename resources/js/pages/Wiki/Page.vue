<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ExternalLink, Star, ArrowUp, History, Calendar, User, FileText, Menu, X, List, ChevronRight, Folder, Search } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout
});

interface PageInfo {
    name: string;
    lastModified: number;
    author: string;
    version: number;
    size: number;
}

function formatDate(timestamp: number) {
    return new Date(timestamp * 1000).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatFileSize(bytes: number): string {
    if (!bytes) return '0 B';
    
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

const props = defineProps<{
    page?: string;
    pageInfo?: PageInfo;
    content: string;
    error?: string;
    extractedTitle?: string;
    subpages?: Array<{id: string, title: string, href?: string, url?: string, type: string, level?: number, isFolder?: boolean}>;
}>();

// Favorite functionality
const isFavorited = ref(false);

// Content sidebar functionality
const showContentSidebar = ref(false);
const tableOfContents = ref<Array<{id: string, text: string, level: number}>>([]);
const subpages = ref<Array<{id: string, title: string, href: string, type: string, level: number, isFolder: boolean}>>([]);
const recentPages = ref<Array<{id: string, title: string, href: string, visitedAt: Date}>>([]);

// Search functionality for team:index (dashboard)
const searchQuery = ref('');

function performSearch() {
    if (searchQuery.value.trim()) {
        // Emit event to open search widget with pre-filled query
        window.dispatchEvent(new CustomEvent('open-search-widget', { 
            detail: { query: searchQuery.value } 
        }));
    } else {
        // Open search widget without query
        window.dispatchEvent(new CustomEvent('open-search-widget'));
    }
}

// Inertia form for favorite toggle
const favoriteForm = useForm({
    page_id: props.page || '',
    page_title: '',
    page_url: ''
});

// Check if page is favorited on mount
onMounted(async () => {
    if (props.page) {
        await checkFavoriteStatus();
        // Set up form data
        favoriteForm.page_id = props.page;
        favoriteForm.page_title = getPageTitle(props.page, props.extractedTitle);
        favoriteForm.page_url = window.location.pathname;
    }
    
    // Wrap tables after content is rendered (mobile only)
    await nextTick();
    wrapTablesWithScrollDiv();
    
    // Extract table of contents and load subpages
    extractTableOfContents();
    loadSubpages();
    
    // Load and add to recent pages
    loadRecentPages();
    addToRecentPages();
    
    // Add resize listener to handle viewport changes
    window.addEventListener('resize', handleResize);
});

// Cleanup on unmount
onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

// Watch for content changes and re-wrap tables
watch(() => props.content, async () => {
    await nextTick();
    wrapTablesWithScrollDiv();
    extractTableOfContents();
});

// Watch for page changes and reload subpages
watch(() => props.page, () => {
    loadSubpages();
    addToRecentPages();
});

// Watch for subpages prop changes
watch(() => props.subpages, () => {
    loadSubpages();
}, { immediate: true });

// Function to wrap tables with overflow div (mobile/tablet only)
function wrapTablesWithScrollDiv() {
    // Only apply table wrapping on mobile and tablet viewports (< 1024px)
    if (window.innerWidth >= 1024) {
        // On desktop, remove any existing wrappers and let CSS handle auto-breaking
        unwrapTables();
        return;
    }
    
    const tables = document.querySelectorAll('.wiki-content table:not(.table-wrapped)');
    tables.forEach(table => {
        // Find the actual wiki-content container and Card
        const wikiContent = table.closest('.wiki-content');
        const card = table.closest('[role="none"]') || table.closest('.min-h-96'); // Card element
        
        // Create wrapper div
        const wrapper = document.createElement('div');
        wrapper.className = 'overflow-x-auto table-scroll-wrapper';
        wrapper.style.margin = '1.5rem 0';
        wrapper.style.width = '100%';
        wrapper.style.boxSizing = 'border-box';
        
        // Try different containers to get the right width
        let maxWidth = '100%';
        
        if (card) {
            const cardRect = card.getBoundingClientRect();
            // Subtract padding (typically 2rem total for CardContent)
            maxWidth = `${cardRect.width - 32}px`;
        } else if (wikiContent) {
            const contentRect = wikiContent.getBoundingClientRect();
            maxWidth = `${contentRect.width}px`;
        }
        
        wrapper.style.maxWidth = maxWidth;
        
        // Mark table as wrapped
        table.classList.add('table-wrapped');
        
        // Insert wrapper before table and move table into wrapper
        table.parentNode?.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
}

// Function to unwrap tables (for desktop)
function unwrapTables() {
    const wrappers = document.querySelectorAll('.table-scroll-wrapper');
    wrappers.forEach(wrapper => {
        const table = wrapper.querySelector('table');
        if (table) {
            // Remove wrapped class
            table.classList.remove('table-wrapped');
            // Move table back to original parent
            wrapper.parentNode?.insertBefore(table, wrapper);
            // Remove wrapper
            wrapper.remove();
        }
    });
}

// Handle window resize to update table wrapping
function handleResize() {
    // Debounce resize events
    clearTimeout(window.resizeTimeout);
    window.resizeTimeout = setTimeout(() => {
        wrapTablesWithScrollDiv();
    }, 150);
}

async function checkFavoriteStatus() {
    if (!props.page) return;
    
    try {
        const response = await fetch(`/api/favorites/${encodeURIComponent(props.page)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        const data = await response.json();
        isFavorited.value = data.is_favorited;
    } catch (error) {
        console.error('Failed to check favorite status:', error);
    }
}

function toggleFavorite() {
    if (!props.page || favoriteForm.processing) return;
    
    // Update form data
    favoriteForm.page_id = props.page;
    favoriteForm.page_title = getPageTitle(props.page, props.extractedTitle);
    favoriteForm.page_url = window.location.pathname;
    
    favoriteForm.post('/api/favorites/toggle', {
        preserveScroll: true,
        onSuccess: (page) => {
            // Extract response data from page props if needed
            isFavorited.value = !isFavorited.value;
            
            // Emit event to update sidebar
            window.dispatchEvent(new CustomEvent('favorites-updated'));
        },
        onError: (errors) => {
            console.error('Failed to toggle favorite:', errors);
        }
    });
}

function getPageTitle(page: string | undefined | null, extractedTitle?: string): string {
    // Use extracted title if available
    if (extractedTitle) {
        // Decode HTML entities (e.g., &#039; becomes ')
        const textarea = document.createElement('textarea');
        textarea.innerHTML = extractedTitle;
        return textarea.value;
    }
    
    if (!page) return 'Unknown Page';
    
    const parts = page.split(':');
    const lastPart = parts[parts.length - 1];
    
    // If the last part is 'index', use the previous namespace name
    if (lastPart === 'index' && parts.length > 1) {
        const previousPart = parts[parts.length - 2];
        return previousPart.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    // Otherwise use the last part, formatted nicely
    return lastPart.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}


function goUp() {
    if (!props.page) return;
    
    const parts = props.page.split(':');
    
    if (parts.length <= 1) {
        // Already at top level, go to wiki index
        router.visit('/wiki');
        return;
    }
    
    // Remove the last part and go up one level
    if (parts[parts.length - 1] === 'index') {
        // If current page is an index, go to parent namespace
        parts.pop(); // Remove 'index'
        if (parts.length > 0) {
            parts.pop(); // Remove the parent namespace part
        }
    } else {
        // If not an index, just remove the last part
        parts.pop();
    }
    
    if (parts.length === 0) {
        router.visit('/wiki');
    } else {
        const parentPage = parts.join(':') + ':index';
        router.visit(route('wiki.show', { page: parentPage }));
    }
}

// Extract table of contents from rendered content
async function extractTableOfContents() {
    tableOfContents.value = [];
    
    if (!props.content) return;
    
    await nextTick();
    
    const headings = document.querySelectorAll('.wiki-content h1, .wiki-content h2, .wiki-content h3, .wiki-content h4, .wiki-content h5, .wiki-content h6');
    
    headings.forEach((heading, index) => {
        const level = parseInt(heading.tagName.charAt(1));
        const text = heading.textContent?.trim() || '';
        const id = heading.id || `heading-${index}`;
        
        // Add ID if not present
        if (!heading.id) {
            heading.id = id;
        }
        
        tableOfContents.value.push({
            id,
            text,
            level
        });
    });
}

// Load subpages from props (no AJAX needed)
function loadSubpages() {
    if (!props.subpages) {
        subpages.value = [];
        return;
    }
    
    // Check if subpages are hidden due to limit (contains _meta object)
    if (props.subpages._meta && props.subpages._meta.hidden_due_to_limit) {
        // Store the meta information for the UI to handle
        subpages.value = props.subpages;
        return;
    }
    
    // Normal array processing
    if (Array.isArray(props.subpages)) {
        subpages.value = props.subpages.map((page: any) => ({
            id: page.id,
            title: page.title || formatPageTitle(page.id),
            href: page.href || page.url || route('wiki.show', { page: page.id }),
            type: page.type || 'page',
            level: page.level || 1,
            isFolder: page.isFolder || false
        }));
    } else {
        subpages.value = [];
    }
}

function formatPageTitle(pageId: string): string {
    const parts = pageId.split(':');
    const lastPart = parts[parts.length - 1];
    return lastPart.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function scrollToHeading(headingId: string) {
    const element = document.getElementById(headingId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        // Close sidebar on mobile after navigation
        if (window.innerWidth < 768) {
            showContentSidebar.value = false;
        }
    }
}

function loadRecentPages() {
    try {
        const stored = localStorage.getItem('wiki_recent_pages');
        if (stored) {
            const parsed = JSON.parse(stored);
            recentPages.value = parsed.map((item: any) => ({
                ...item,
                visitedAt: new Date(item.visitedAt)
            })).slice(0, 10);
        }
    } catch (error) {
        console.error('Failed to load recent pages:', error);
        recentPages.value = [];
    }
}

function addToRecentPages() {
    if (!props.page) return;
    
    const currentPage = {
        id: props.page,
        title: getPageTitle(props.page, props.extractedTitle),
        href: window.location.pathname,
        visitedAt: new Date()
    };

    // Remove existing entry for this page if it exists
    recentPages.value = recentPages.value.filter(page => page.id !== currentPage.id);
    
    // Add to the beginning
    recentPages.value.unshift(currentPage);
    
    // Keep only the last 10 entries
    recentPages.value = recentPages.value.slice(0, 10);

    try {
        localStorage.setItem('wiki_recent_pages', JSON.stringify(recentPages.value));
        // Dispatch event to update sidebar
        window.dispatchEvent(new CustomEvent('recent-pages-updated'));
    } catch (error) {
        console.error('Failed to save recent pages:', error);
    }
}
</script>

<template>
    <Head :title="`${getPageTitle(props.page, props.extractedTitle)} - Wiki`" />

        <div class="w-full py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- Header -->
                <div class="mb-6">
                    <!-- Desktop layout -->
                    <div class="hidden sm:flex items-center justify-between mb-4">
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ getPageTitle(props.page, props.extractedTitle) }}
                            </h1>
                            <!-- Search bar for team:index (dashboard) -->
                            <div v-if="props.page === 'team:index'" class="flex gap-2 mt-4 max-w-md">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search wiki pages..."
                                    class="flex-1"
                                    @keyup.enter="performSearch"
                                />
                                <Button @click="performSearch" class="flex items-center gap-2">
                                    <Search class="h-4 w-4" />
                                    Search
                                </Button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Favorite button (icon only) -->
                            <Button 
                                v-if="props.page"
                                @click="toggleFavorite"
                                :disabled="favoriteForm.processing"
                                variant="outline" 
                                size="sm"
                                class="flex items-center"
                                :class="{ 'text-yellow-600 border-yellow-300': isFavorited }"
                                :title="isFavorited ? 'Remove from favorites' : 'Add to favorites'"
                            >
                                <Star 
                                    class="h-4 w-4" 
                                    :class="{ 'fill-yellow-400': isFavorited }"
                                />
                            </Button>

                            <!-- Open in DokuWiki button -->
                            <Button 
                                v-if="props.page"
                                as="a" 
                                :href="`https://wiki.eurofurence.org/doku.php?id=${props.page}`"
                                target="_blank"
                                variant="outline" 
                                size="sm"
                                class="flex items-center gap-2"
                            >
                                <ExternalLink class="h-4 w-4" />
                                <span class="hidden lg:inline">Open in DokuWiki</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Mobile layout -->
                    <div class="sm:hidden mb-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                            {{ getPageTitle(props.page, props.extractedTitle) }}
                        </h1>
                        <!-- Search bar for team:index (dashboard) -->
                        <div v-if="props.page === 'team:index'" class="flex gap-2 mb-3">
                            <Input
                                v-model="searchQuery"
                                placeholder="Search wiki pages..."
                                class="flex-1"
                                @keyup.enter="performSearch"
                            />
                            <Button @click="performSearch" class="flex items-center gap-2">
                                <Search class="h-4 w-4" />
                                Search
                            </Button>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Favorite button (icon only) -->
                            <Button 
                                v-if="props.page"
                                @click="toggleFavorite"
                                :disabled="favoriteForm.processing"
                                variant="outline" 
                                size="sm"
                                class="flex items-center"
                                :class="{ 'text-yellow-600 border-yellow-300': isFavorited }"
                                :title="isFavorited ? 'Remove from favorites' : 'Add to favorites'"
                            >
                                <Star 
                                    class="h-4 w-4" 
                                    :class="{ 'fill-yellow-400': isFavorited }"
                                />
                            </Button>

                            <!-- Open in DokuWiki button -->
                            <Button 
                                v-if="props.page"
                                as="a" 
                                :href="`https://wiki.eurofurence.org/doku.php?id=${props.page}`"
                                target="_blank"
                                variant="outline" 
                                size="sm"
                                class="flex items-center"
                                title="Open in DokuWiki"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Error Alert -->
                <div v-if="props.error" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md flex items-center gap-2">
                    <AlertCircle class="h-5 w-5 text-red-500 dark:text-red-400" />
                    <span class="text-red-700 dark:text-red-300">{{ props.error }}</span>
                </div>

                <!-- Main Content with Sidebar -->
                <div class="flex gap-6">
                    <!-- Main Content -->
                    <div class="flex-1 min-w-0">
                        <Card class="min-h-96 w-full">
                            <CardContent>
                                <div 
                                    v-if="props.content" 
                                    class="wiki-content max-w-none"
                                    v-html="props.content"
                                ></div>
                                <div v-else-if="!props.error" class="text-gray-500 text-center py-8">
                                    This page is empty or does not exist.
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Content Sidebar -->
                    <div class="hidden lg:block w-80 flex-shrink-0">
                        <div class="sticky top-6 space-y-4">
                            <!-- Table of Contents -->
                            <Card v-if="tableOfContents.length > 0">
                                <CardHeader>
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <List class="h-4 w-4" />
                                        Table of Contents
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <nav class="space-y-1">
                                        <button
                                            v-for="item in tableOfContents"
                                            :key="item.id"
                                            @click="scrollToHeading(item.id)"
                                            class="block w-full text-left text-sm hover:text-blue-600 transition-colors"
                                            :class="{
                                                'ml-0': item.level === 1,
                                                'ml-4': item.level === 2,
                                                'ml-8': item.level === 3,
                                                'ml-12': item.level === 4,
                                                'ml-16': item.level === 5,
                                                'ml-20': item.level === 6,
                                            }"
                                        >
                                            {{ item.text }}
                                        </button>
                                    </nav>
                                </CardContent>
                            </Card>

                            <!-- Subpages -->
                            <Card v-if="subpages.length > 0 || (subpages._meta && subpages._meta.hidden_due_to_limit)">
                                <CardHeader>
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <FileText class="h-4 w-4" />
                                        Subpages
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <!-- Hidden due to limit message -->
                                    <div v-if="subpages._meta && subpages._meta.hidden_due_to_limit" class="text-center py-4 text-gray-600">
                                        <FileText class="h-8 w-8 mx-auto mb-2 text-gray-400" />
                                        <p class="text-sm font-medium">Too many subpages to display</p>
                                        <p class="text-xs text-gray-500">
                                            {{ subpages._meta.total_count }} subpages found. Use search to find specific pages.
                                        </p>
                                    </div>
                                    <!-- Normal subpages list -->
                                    <nav v-else class="space-y-1">
                                        <a
                                            v-for="page in subpages"
                                            :key="page.id"
                                            :href="page.href"
                                            class="block text-sm hover:text-blue-600 transition-colors flex items-center gap-2 py-1"
                                            :class="{ 'ml-4': page.level === 2 }"
                                        >
                                            <Folder v-if="page.type === 'folder'" class="h-3 w-3 flex-shrink-0 text-amber-600" />
                                            <FileText v-else class="h-3 w-3 flex-shrink-0 text-gray-500" />
                                            <span class="truncate">{{ page.title }}</span>
                                        </a>
                                    </nav>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Mobile Sidebar Toggle -->
                    <Button
                        v-if="tableOfContents.length > 0 || subpages.length > 0"
                        @click="showContentSidebar = true"
                        class="lg:hidden fixed bottom-6 right-6 rounded-full w-12 h-12 shadow-lg z-50"
                        size="sm"
                    >
                        <Menu class="h-5 w-5" />
                    </Button>
                </div>

                <!-- Mobile Sidebar Drawer -->
                <div
                    v-if="showContentSidebar"
                    class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-50"
                    @click="showContentSidebar = false"
                >
                    <div
                        class="fixed right-0 top-0 h-full w-80 bg-white shadow-lg transform transition-transform duration-300 ease-in-out overflow-y-auto"
                        @click.stop
                    >
                        <div class="p-4 border-b">
                            <div class="flex items-center justify-between">
                                <h2 class="font-semibold">Page Navigation</h2>
                                <Button @click="showContentSidebar = false" variant="ghost" size="sm">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="p-4 space-y-4">
                            <!-- Table of Contents -->
                            <Card v-if="tableOfContents.length > 0">
                                <CardHeader>
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <List class="h-4 w-4" />
                                        Table of Contents
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <nav class="space-y-1">
                                        <button
                                            v-for="item in tableOfContents"
                                            :key="item.id"
                                            @click="scrollToHeading(item.id)"
                                            class="block w-full text-left text-sm hover:text-blue-600 transition-colors"
                                            :class="{
                                                'ml-0': item.level === 1,
                                                'ml-4': item.level === 2,
                                                'ml-8': item.level === 3,
                                                'ml-12': item.level === 4,
                                                'ml-16': item.level === 5,
                                                'ml-20': item.level === 6,
                                            }"
                                        >
                                            {{ item.text }}
                                        </button>
                                    </nav>
                                </CardContent>
                            </Card>

                            <!-- Subpages -->
                            <Card v-if="subpages.length > 0 || (subpages._meta && subpages._meta.hidden_due_to_limit)">
                                <CardHeader>
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <FileText class="h-4 w-4" />
                                        Subpages
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <!-- Hidden due to limit message -->
                                    <div v-if="subpages._meta && subpages._meta.hidden_due_to_limit" class="text-center py-4 text-gray-600">
                                        <FileText class="h-8 w-8 mx-auto mb-2 text-gray-400" />
                                        <p class="text-sm font-medium">Too many subpages to display</p>
                                        <p class="text-xs text-gray-500">
                                            {{ subpages._meta.total_count }} subpages found. Use search to find specific pages.
                                        </p>
                                    </div>
                                    <!-- Normal subpages list -->
                                    <nav v-else class="space-y-1">
                                        <a
                                            v-for="page in subpages"
                                            :key="page.id"
                                            :href="page.href"
                                            class="block text-sm hover:text-blue-600 transition-colors flex items-center gap-2 py-1"
                                            :class="{ 'ml-4': page.level === 2 }"
                                        >
                                            <Folder v-if="page.type === 'folder'" class="h-3 w-3 flex-shrink-0 text-amber-600" />
                                            <FileText v-else class="h-3 w-3 flex-shrink-0 text-gray-500" />
                                            <span class="truncate">{{ page.title }}</span>
                                        </a>
                                    </nav>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>

                <!-- Page Footer -->
                <div v-if="props.page && !props.error" class="mt-8 space-y-4">
                    <!-- Action Buttons -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            @click="goUp"
                            variant="outline"
                            size="sm"
                            class="flex items-center gap-2"
                        >
                            <ArrowUp class="h-4 w-4" />
                            Up
                        </Button>

                        <Button
                            :href="route('wiki.history', { page: props.page })"
                            as="a"
                            variant="outline"
                            size="sm"
                            class="flex items-center gap-2"
                        >
                            <History class="h-4 w-4" />
                            Page History
                        </Button>
                    </div>

                    <!-- Page Information -->
                    <Card v-if="props.pageInfo" class="bg-gray-50 dark:bg-gray-800/50">
                        <CardContent class="p-4">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                <FileText class="h-4 w-4" />
                                Page Information
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <div class="text-gray-600 dark:text-gray-400">Last Modified</div>
                                        <div class="font-medium">{{ formatDate(props.pageInfo.lastModified) }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <div class="text-gray-600 dark:text-gray-400">Author</div>
                                        <div class="font-medium">{{ props.pageInfo.author || 'Unknown' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <div class="text-gray-600 dark:text-gray-400">Version</div>
                                        <div class="font-medium">{{ props.pageInfo.version || 'N/A' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <div class="text-gray-600 dark:text-gray-400">Size</div>
                                        <div class="font-medium">{{ formatFileSize(props.pageInfo.size) }}</div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
</template>

<style>
/* Wiki Content Styling */
.wiki-content {
    color: #1f2937;
    line-height: 1.7;
    font-size: 16px;
}

.dark .wiki-content {
    color: #e5e7eb;
}

/* Typography */
.wiki-content h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: #111827;
    margin: 2rem 0 1.5rem 0;
    line-height: 1.2;
    border-bottom: 3px solid #e5e7eb;
    padding-bottom: 0.5rem;
}

.dark .wiki-content h1 {
    color: #f9fafb;
    border-bottom-color: #374151;
}

.wiki-content h2 {
    font-size: 2rem;
    font-weight: 600;
    color: #111827;
    margin: 1.75rem 0 1rem 0;
    line-height: 1.3;
    border-bottom: 2px solid #f3f4f6;
    padding-bottom: 0.25rem;
}

.dark .wiki-content h2 {
    color: #f3f4f6;
    border-bottom-color: #4b5563;
}

.wiki-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin: 1.5rem 0 0.75rem 0;
    line-height: 1.4;
}

.dark .wiki-content h3 {
    color: #e5e7eb;
}

.wiki-content h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin: 1.25rem 0 0.5rem 0;
    line-height: 1.4;
}

.dark .wiki-content h4 {
    color: #e5e7eb;
}

.wiki-content h5 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #374151;
    margin: 1rem 0 0.5rem 0;
    line-height: 1.4;
}

.dark .wiki-content h5 {
    color: #d1d5db;
}

.wiki-content h6 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 1rem 0 0.5rem 0;
    line-height: 1.4;
}

.dark .wiki-content h6 {
    color: #d1d5db;
}

/* Paragraphs and text */
.wiki-content p {
    margin: 1rem 0;
    line-height: 1.7;
}

.wiki-content p:first-child {
    margin-top: 0;
}

.wiki-content p:last-child {
    margin-bottom: 0;
}

/* Lists */
.wiki-content ul,
.wiki-content ol {
    margin: 1rem 0;
    padding-left: 2rem;
}

.wiki-content li {
    margin: 0;
    line-height: 1.6;
}

.wiki-content ul li {
    list-style-type: disc;
}

.wiki-content ol li {
    list-style-type: decimal;
}

.wiki-content ul ul,
.wiki-content ol ol,
.wiki-content ul ol,
.wiki-content ol ul {
    margin: 0.25rem 0;
}

/* Links */
.wiki-content a {
    color: #2563eb;
    text-decoration: none;
    transition: color 0.2s ease;
}

.wiki-content a:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.wiki-content a:visited {
    color: #7c3aed;
}

.dark .wiki-content a {
    color: #60a5fa;
}

.dark .wiki-content a:hover {
    color: #93c5fd;
}

.dark .wiki-content a:visited {
    color: #a78bfa;
}

/* Tables */
.wiki-content table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.dark .wiki-content table {
    background: #1f2937;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.3);
}

.wiki-content th,
.wiki-content td {
    border: 1px solid #e5e7eb;
    padding: 0.875rem 1rem;
    text-align: left;
    vertical-align: top;
}

.dark .wiki-content th,
.dark .wiki-content td {
    border-color: #4b5563;
}

/* Mobile/Tablet: Allow horizontal scroll via JavaScript wrapper */
@media (max-width: 1023px) {
    .wiki-content th,
    .wiki-content td {
        white-space: nowrap;
    }
}

/* Desktop: Auto-break content to prevent overflow */
@media (min-width: 1024px) {
    .wiki-content table {
        table-layout: fixed;
        width: 100%;
    }
    
    .wiki-content th,
    .wiki-content td {
        word-wrap: break-word;
        word-break: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        white-space: normal;
    }
}

.wiki-content th {
    background-color: #f8fafc;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #d1d5db;
}

.dark .wiki-content th {
    background-color: #374151;
    color: #e5e7eb;
    border-bottom-color: #6b7280;
}

.wiki-content tr:nth-child(even) {
    background-color: #f9fafb;
}

.dark .wiki-content tr:nth-child(even) {
    background-color: #374151;
}

/* Code */
.wiki-content code {
    background-color: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    border: 1px solid #e2e8f0;
}

.dark .wiki-content code {
    background-color: #374151;
    color: #d1d5db;
    border-color: #4b5563;
}

.wiki-content pre {
    background-color: #1e293b;
    color: #e2e8f0;
    padding: 1.5rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1.5rem 0;
    line-height: 1.5;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.dark .wiki-content pre {
    background-color: #111827;
    color: #f3f4f6;
}

.wiki-content pre code {
    background: none;
    border: none;
    padding: 0;
    color: inherit;
    font-size: 0.875rem;
}

/* Blockquotes */
.wiki-content blockquote {
    border-left: 4px solid #3b82f6;
    background-color: #eff6ff;
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    border-radius: 0 0.5rem 0.5rem 0;
    font-style: italic;
    color: #1e40af;
}

.dark .wiki-content blockquote {
    background-color: #1e3a8a;
    color: #93c5fd;
    border-left-color: #60a5fa;
}

.wiki-content blockquote p {
    margin: 0;
}

/* Images */
.wiki-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.wiki-content img.media {
    display: block;
    margin: 1.5rem auto;
}

/* Horizontal rules */
.wiki-content hr {
    border: none;
    height: 2px;
    background: linear-gradient(to right, #e5e7eb, #9ca3af, #e5e7eb);
    margin: 2rem 0;
    border-radius: 1px;
}

/* DokuWiki specific classes */
.wiki-content .level1,
.wiki-content .level2,
.wiki-content .level3,
.wiki-content .level4,
.wiki-content .level5 {
    margin: 0;
}

.wiki-content .clearer {
    clear: both;
}

/* Info boxes and notes */
.wiki-content .info,
.wiki-content .warning,
.wiki-content .tip {
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    border-radius: 0.5rem;
    border-left: 4px solid;
}

.wiki-content .info {
    background-color: #eff6ff;
    border-color: #3b82f6;
    color: #1e40af;
}

.dark .wiki-content .info {
    background-color: #1e3a8a;
    color: #93c5fd;
}

.wiki-content .warning {
    background-color: #fef3cd;
    border-color: #f59e0b;
    color: #92400e;
}

.dark .wiki-content .warning {
    background-color: #92400e;
    color: #fbbf24;
}

.wiki-content .tip {
    background-color: #f0fdf4;
    border-color: #10b981;
    color: #065f46;
}

.dark .wiki-content .tip {
    background-color: #065f46;
    color: #34d399;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
    .wiki-content {
        font-size: 14px;
    }
    
    .wiki-content h1 {
        font-size: 2rem;
        margin: 1.5rem 0 1rem 0;
    }
    
    .wiki-content h2 {
        font-size: 1.5rem;
        margin: 1.25rem 0 0.75rem 0;
    }
    
    .wiki-content h3 {
        font-size: 1.25rem;
        margin: 1rem 0 0.5rem 0;
    }
    
    .wiki-content table {
        font-size: 0.875rem;
    }
    
    .wiki-content th,
    .wiki-content td {
        padding: 0.5rem 0.75rem;
    }
    
    .wiki-content pre {
        padding: 1rem;
        font-size: 0.75rem;
    }
    
    .wiki-content blockquote {
        padding: 0.75rem 1rem;
        margin: 1rem 0;
    }
    
    .wiki-content ul,
    .wiki-content ol {
        padding-left: 1.5rem;
    }
}

/* Small mobile screens */
@media (max-width: 480px) {
    .wiki-content {
        font-size: 13px;
    }
    
    .wiki-content h1 {
        font-size: 1.75rem;
    }
    
    .wiki-content h2 {
        font-size: 1.375rem;
    }
    
    .wiki-content table {
        font-size: 0.75rem;
    }
}
</style>