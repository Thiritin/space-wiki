<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { router } from '@inertiajs/vue3'
import { Search, X, FileText, Folder } from 'lucide-vue-next'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

interface SearchResult {
    id: string
    title: string
    content: string
    namespace: string
    url: string
    last_modified: number
    last_modified_human: string
    _highlightResult: {
        title: {
            value: string
            matchLevel: string
        }
        content: {
            value: string
            matchLevel: string
        }
    }
}

interface SearchResponse {
    hits: SearchResult[]
    query: string
    processing_time_ms: number
    nb_hits: number
}

const isOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref<SearchResult[]>([])
const isLoading = ref(false)
const selectedIndex = ref(-1)
const searchInput = ref<HTMLInputElement>()
const searchContainer = ref<HTMLDivElement>()

let searchTimeout: ReturnType<typeof setTimeout>

const hasResults = computed(() => searchResults.value.length > 0)

const openSearch = () => {
    isOpen.value = true
    nextTick(() => {
        setTimeout(() => {
            searchInput.value?.focus()
        }, 50)
    })
}

const closeSearch = () => {
    isOpen.value = false
    searchQuery.value = ''
    searchResults.value = []
    selectedIndex.value = -1
}

const performSearch = async (query: string) => {
    if (!query.trim()) {
        searchResults.value = []
        return
    }

    isLoading.value = true
    
    try {
        const response = await fetch(`/api/wiki/search?q=${encodeURIComponent(query)}&limit=8`)
        const data: SearchResponse = await response.json()
        searchResults.value = data.hits
    } catch (error) {
        console.error('Search failed:', error)
        searchResults.value = []
    } finally {
        isLoading.value = false
    }
}

const debouncedSearch = (query: string) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        performSearch(query)
    }, 300)
}

const selectResult = (result: SearchResult) => {
    router.visit(result.url)
    closeSearch()
}


const handleKeydown = (event: KeyboardEvent) => {
    if (!isOpen.value) {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault()
            openSearch()
        }
        return
    }

    switch (event.key) {
        case 'Escape':
            event.preventDefault()
            closeSearch()
            break
        case 'ArrowDown':
            event.preventDefault()
            selectedIndex.value = Math.min(selectedIndex.value + 1, searchResults.value.length - 1)
            break
        case 'ArrowUp':
            event.preventDefault()
            selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
            break
        case 'Enter':
            event.preventDefault()
            if (selectedIndex.value >= 0 && searchResults.value[selectedIndex.value]) {
                selectResult(searchResults.value[selectedIndex.value])
            }
            // Don't close search if no result is selected - keep it open for further searching
            break
    }
}

const handleClickOutside = (event: MouseEvent) => {
    if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
        closeSearch()
    }
}

watch(searchQuery, (newQuery) => {
    selectedIndex.value = -1
    debouncedSearch(newQuery)
})

const handleOpenSearchWidget = (event: CustomEvent) => {
    openSearch()
    if (event.detail?.query) {
        nextTick(() => {
            searchQuery.value = event.detail.query
            debouncedSearch(event.detail.query)
        })
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
    document.addEventListener('mousedown', handleClickOutside)
    window.addEventListener('open-search-widget', handleOpenSearchWidget)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    document.removeEventListener('mousedown', handleClickOutside)
    window.removeEventListener('open-search-widget', handleOpenSearchWidget)
    clearTimeout(searchTimeout)
})

defineExpose({
    openSearch,
    closeSearch,
})
</script>

<template>
    <div ref="searchContainer" class="relative">
        <!-- Search Trigger Button -->
        <Button 
            variant="ghost" 
            size="sm" 
            class="flex items-center gap-2 text-muted-foreground hover:text-foreground"
            @click="openSearch"
        >
            <Search class="h-4 w-4" />
            <span class="hidden sm:inline">Search...</span>
            <kbd class="hidden sm:inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground ml-auto">
                <span class="text-xs">⌘</span>K
            </kbd>
        </Button>

        <!-- Search Modal/Popup -->
        <div 
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/80 backdrop-blur-sm p-4 sm:p-6 md:p-20"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-lg border bg-background text-popover-foreground shadow-2xl">
                <!-- Search Input -->
                <div class="flex items-center border-b px-3">
                    <Search class="mr-2 h-4 w-4 shrink-0 opacity-50" />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        placeholder="Search wiki pages..."
                        class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none border-0 ring-0 focus:ring-0 placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        autofocus
                    />
                    <Button variant="ghost" size="sm" class="h-7 w-7 p-0" @click="closeSearch">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Search Results -->
                <div class="max-h-[400px] overflow-x-hidden overflow-y-auto">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="p-4 text-center text-sm text-muted-foreground">
                        <div class="flex items-center justify-center space-x-2">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                            <span>Searching...</span>
                        </div>
                    </div>

                    <!-- No Results -->
                    <div v-else-if="searchQuery && !hasResults && !isLoading" class="p-8 text-center text-sm text-muted-foreground">
                        <FileText class="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p class="font-medium">No results found</p>
                        <p class="text-xs">Try different keywords</p>
                    </div>

                    <!-- Results List -->
                    <div v-else-if="hasResults" class="p-2">
                        <div
                            v-for="(result, index) in searchResults"
                            :key="result.id"
                            class="flex cursor-pointer select-none items-start rounded-sm px-2 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                            :class="{
                                'bg-accent text-accent-foreground': index === selectedIndex
                            }"
                            @click="selectResult(result)"
                            @mouseenter="selectedIndex = index"
                        >
                            <div class="flex h-4 w-4 shrink-0 items-center justify-center">
                                <FileText class="h-3 w-3" />
                            </div>
                            <div class="ml-2 flex-1 overflow-hidden">
                                <div 
                                    class="truncate font-medium"
                                    v-html="result._highlightResult.title.value"
                                ></div>
                                <div 
                                    class="text-xs text-muted-foreground line-clamp-2 mt-0.5 leading-relaxed"
                                    v-html="result._highlightResult.content.value"
                                ></div>
                                <div class="flex items-center space-x-2 text-xs text-muted-foreground mt-1">
                                    <Folder class="h-3 w-3" />
                                    <span>{{ result.namespace }}</span>
                                    <span class="text-xs opacity-50">•</span>
                                    <span class="text-xs">{{ result.last_modified_human }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!searchQuery" class="p-8 text-center text-sm text-muted-foreground">
                        <Search class="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p class="font-medium">Search Wiki Pages</p>
                        <p class="text-xs">Start typing to find pages</p>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="hasResults" class="border-t p-2 text-xs text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <kbd class="inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium">
                                ↑↓
                            </kbd>
                            <span>navigate</span>
                            <kbd class="inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium">
                                ↵
                            </kbd>
                            <span>select</span>
                        </div>
                        <span>{{ searchResults.length }} results</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(mark) {
    background-color: rgba(239, 68, 68, 0.15);
    color: rgb(220, 38, 38);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-weight: 600;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>