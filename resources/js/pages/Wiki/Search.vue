<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/vue3';
import { Search, ArrowLeft, AlertCircle, FileText } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout
});

interface SearchResult {
    id: string;
    title?: string;
    snippet?: string;
    score: number;
    lastModified: number;
}

const props = defineProps<{
    query: string;
    results: SearchResult[];
    error?: string;
}>();

const searchQuery = ref(props.query);

onMounted(() => {
    if (searchQuery.value) {
        // Focus on search input if there's already a query
        const input = document.getElementById('search-input') as HTMLInputElement;
        if (input) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }
});

function performSearch() {
    if (searchQuery.value.trim()) {
        router.get(route('wiki.search'), { q: searchQuery.value });
    }
}

function getPageTitle(result: SearchResult): string {
    return result.title || result.id.split(':').pop() || result.id;
}

function formatDate(timestamp: number) {
    return new Date(timestamp * 1000).toLocaleDateString();
}

function highlightText(text: string, query: string): string {
    if (!query || !text) return text;
    
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
}
</script>

<template>
    <Head title="Search Wiki" />

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center gap-4 mb-4">
                        <Button 
                            as="a" 
                            :href="route('wiki.index')" 
                            variant="outline" 
                            size="sm"
                            class="flex items-center gap-2"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to Wiki
                        </Button>
                        
                        <h1 class="text-3xl font-bold text-gray-900 flex-1">
                            Search Wiki
                        </h1>
                    </div>

                    <!-- Search Bar -->
                    <div class="flex gap-2 mb-6">
                        <Input
                            id="search-input"
                            v-model="searchQuery"
                            placeholder="Enter search terms..."
                            class="flex-1"
                            @keyup.enter="performSearch"
                        />
                        <Button @click="performSearch" class="flex items-center gap-2">
                            <Search class="h-4 w-4" />
                            Search
                        </Button>
                    </div>
                </div>

                <!-- Error Alert -->
                <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-md flex items-center gap-2">
                    <AlertCircle class="h-5 w-5 text-red-500" />
                    <span class="text-red-700">{{ error }}</span>
                </div>

                <!-- Search Results -->
                <div v-if="query && !error">
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold">
                            Search results for "{{ query }}"
                        </h2>
                        <p class="text-gray-600">
                            Found {{ results.length }} result{{ results.length !== 1 ? 's' : '' }}
                        </p>
                    </div>

                    <div v-if="results.length === 0" class="text-center py-12">
                        <FileText class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No results found</h3>
                        <p class="text-gray-600 mb-4">
                            Try adjusting your search terms or browse all pages instead.
                        </p>
                        <Button as="a" :href="route('wiki.index')" variant="outline">
                            Browse All Pages
                        </Button>
                    </div>

                    <div v-else class="space-y-4">
                        <Card 
                            v-for="result in results" 
                            :key="result.id"
                            class="hover:shadow-md transition-shadow cursor-pointer"
                            @click="$inertia.visit(route('wiki.show', { page: result.id }))"
                        >
                            <CardHeader class="pb-3">
                                <CardTitle class="text-lg">
                                    <a 
                                        :href="route('wiki.show', { page: result.id })"
                                        class="text-blue-600 hover:text-blue-800 no-underline hover:underline"
                                        v-html="highlightText(getPageTitle(result), query)"
                                    ></a>
                                </CardTitle>
                                <CardDescription class="text-sm">
                                    {{ result.id }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent v-if="result.snippet" class="pt-0">
                                <p 
                                    class="text-gray-700 line-clamp-3" 
                                    v-html="highlightText(result.snippet, query)"
                                ></p>
                                <div class="mt-2 text-sm text-gray-500">
                                    Score: {{ Math.round(result.score * 100) }}% • 
                                    Last modified: {{ formatDate(result.lastModified) }}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="!query && !error" class="text-center py-12">
                    <Search class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Search the Wiki</h3>
                    <p class="text-gray-600 mb-4">
                        Enter keywords to find pages, content, and information across the wiki.
                    </p>
                    
                    <!-- Search Tips -->
                    <Card class="max-w-md mx-auto mt-8">
                        <CardHeader>
                            <CardTitle class="text-base">Search Tips</CardTitle>
                        </CardHeader>
                        <CardContent class="text-left space-y-2">
                            <div class="text-sm">
                                <div class="font-medium">• Use multiple keywords</div>
                                <div class="text-gray-600 ml-4">Find pages containing all terms</div>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium">• Search page names</div>
                                <div class="text-gray-600 ml-4">Find specific pages by title</div>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium">• Content search</div>
                                <div class="text-gray-600 ml-4">Search within page content</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
</template>

<style>
/* Line clamp utility */
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>