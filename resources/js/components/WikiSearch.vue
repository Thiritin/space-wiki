<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { FileText, Folder, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface SearchResult {
    id: string;
    title: string;
    content: string;
    namespace: string;
    url: string;
    last_modified: number;
    last_modified_human: string;
    _highlightResult: {
        title: {
            value: string;
            matchLevel: string;
        };
        content: {
            value: string;
            matchLevel: string;
        };
    };
}

interface SearchResponse {
    hits: SearchResult[];
    query: string;
    processing_time_ms: number;
    nb_hits: number;
}

const isOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const isLoading = ref(false);
const selectedIndex = ref(-1);
const searchInput = ref<HTMLInputElement>();
const searchContainer = ref<HTMLDivElement>();

let searchTimeout: ReturnType<typeof setTimeout>;

const hasResults = computed(() => searchResults.value.length > 0);

const openSearch = () => {
    isOpen.value = true;
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const closeSearch = () => {
    isOpen.value = false;
    searchQuery.value = '';
    searchResults.value = [];
    selectedIndex.value = -1;
};

const performSearch = async (query: string) => {
    if (!query.trim()) {
        searchResults.value = [];
        return;
    }

    isLoading.value = true;
    
    try {
        const response = await fetch(`/api/wiki/search?q=${encodeURIComponent(query)}&limit=8`);
        const data: SearchResponse = await response.json();
        searchResults.value = data.hits;
    } catch (error) {
        console.error('Search failed:', error);
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
};

const debouncedSearch = (query: string) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch(query);
    }, 300);
};

const selectResult = (result: SearchResult) => {
    router.visit(result.url);
    closeSearch();
};

const navigateToSearchPage = () => {
    if (searchQuery.value.trim()) {
        router.visit(`/wiki/search?q=${encodeURIComponent(searchQuery.value)}`);
        closeSearch();
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if (!isOpen.value) {
        if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            openSearch();
        }
        return;
    }

    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            closeSearch();
            break;
        case 'ArrowDown':
            event.preventDefault();
            selectedIndex.value = Math.min(selectedIndex.value + 1, searchResults.value.length - 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            selectedIndex.value = Math.max(selectedIndex.value - 1, -1);
            break;
        case 'Enter':
            event.preventDefault();
            if (selectedIndex.value >= 0 && searchResults.value[selectedIndex.value]) {
                selectResult(searchResults.value[selectedIndex.value]);
            } else {
                navigateToSearchPage();
            }
            break;
    }
};

const handleClickOutside = (event: MouseEvent) => {
    if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
        closeSearch();
    }
};

watch(searchQuery, (newQuery) => {
    selectedIndex.value = -1;
    debouncedSearch(newQuery);
});

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('mousedown', handleClickOutside);
    clearTimeout(searchTimeout);
});

defineExpose({
    openSearch,
    closeSearch,
});
</script>

<template>
    <div ref="searchContainer" class="relative">
        <!-- Search Trigger Button -->
        <Button 
            variant="ghost" 
            size="icon" 
            class="group h-9 w-9 cursor-pointer bg-blue-100 hover:bg-blue-200 border-2 border-blue-300"
            @click="openSearch"
            title="Search (Cmd+K)"
        >
            <Search class="size-5 opacity-80 group-hover:opacity-100" />
        </Button>

        <!-- Search Modal/Popup -->
        <div 
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm"
        >
            <div class="mt-20 w-full max-w-2xl mx-4">
                <div class="bg-white dark:bg-neutral-900 rounded-lg shadow-2xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                    <!-- Search Input -->
                    <div class="flex items-center px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
                        <Search class="size-5 text-neutral-400 mr-3 flex-shrink-0" />
                        <Input
                            ref="searchInput"
                            v-model="searchQuery"
                            placeholder="Search wiki pages..."
                            class="border-0 bg-transparent text-lg placeholder:text-neutral-400 focus:ring-0 flex-1"
                            autofocus
                        />
                        <div class="flex items-center space-x-2 ml-3">
                            <kbd class="hidden sm:inline-block px-2 py-1 text-xs font-semibold text-neutral-500 bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded">
                                ESC
                            </kbd>
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="closeSearch">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="max-h-96 overflow-y-auto">
                        <!-- Loading State -->
                        <div v-if="isLoading" class="p-4 text-center text-neutral-500">
                            <div class="flex items-center justify-center space-x-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                <span>Searching...</span>
                            </div>
                        </div>

                        <!-- No Results -->
                        <div v-else-if="searchQuery && !hasResults && !isLoading" class="p-8 text-center text-neutral-500">
                            <FileText class="size-12 mx-auto mb-3 opacity-50" />
                            <p class="text-lg font-medium mb-1">No results found</p>
                            <p class="text-sm">Try searching with different keywords</p>
                        </div>

                        <!-- Results List -->
                        <div v-else-if="hasResults" class="py-2">
                            <div
                                v-for="(result, index) in searchResults"
                                :key="result.id"
                                class="group cursor-pointer px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                                :class="{
                                    'bg-neutral-50 dark:bg-neutral-800': index === selectedIndex
                                }"
                                @click="selectResult(result)"
                                @mouseenter="selectedIndex = index"
                            >
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <FileText class="size-4 text-neutral-400 group-hover:text-blue-600" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <h3 
                                                class="font-medium text-neutral-900 dark:text-neutral-100 truncate"
                                                v-html="result._highlightResult.title.value"
                                            ></h3>
                                            <div class="flex items-center space-x-1 text-xs text-neutral-500 flex-shrink-0">
                                                <Folder class="size-3" />
                                                <span>{{ result.namespace }}</span>
                                            </div>
                                        </div>
                                        <p 
                                            class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-2"
                                            v-html="result._highlightResult.content.value"
                                        ></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="!searchQuery" class="p-8 text-center text-neutral-500">
                            <Search class="size-12 mx-auto mb-3 opacity-50" />
                            <p class="text-lg font-medium mb-1">Search Wiki Pages</p>
                            <p class="text-sm mb-4">Find documentation, guides, and more</p>
                            <div class="flex items-center justify-center space-x-1 text-xs">
                                <kbd class="px-2 py-1 font-semibold bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded">
                                    ⌘K
                                </kbd>
                                <span>to search</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div v-if="hasResults" class="px-4 py-3 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
                        <div class="flex items-center justify-between text-xs text-neutral-500">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-1">
                                    <kbd class="px-1.5 py-0.5 font-semibold bg-white dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded text-xs">↑↓</kbd>
                                    <span>navigate</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <kbd class="px-1.5 py-0.5 font-semibold bg-white dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded text-xs">↵</kbd>
                                    <span>select</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <kbd class="px-1.5 py-0.5 font-semibold bg-white dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded text-xs">esc</kbd>
                                    <span>close</span>
                                </div>
                            </div>
                            <span>{{ searchResults.length }} results</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

:deep(mark) {
    background-color: rgba(59, 130, 246, 0.2);
    color: rgb(59, 130, 246);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-weight: 500;
}

:deep(.dark mark) {
    background-color: rgba(59, 130, 246, 0.3);
    color: rgb(147, 197, 253);
}
</style>