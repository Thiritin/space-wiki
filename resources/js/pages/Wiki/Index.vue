<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, Clock, FileText, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineOptions({
    layout: AppLayout,
});

interface Page {
    id: string;
    title?: string;
    size: number;
    lastModified: number;
}

interface RecentChange {
    id?: string;
    revision?: number;
    author?: string;
    ip?: string;
    summary?: string;
    type?: string;
    sizechange?: number;
}

interface PageInfo {
    name: string;
    lastModified: number;
    author: string;
    version: number;
    size: number;
}

defineProps<{
    pages: Page[];
    recentChanges: RecentChange[];
    indexPageContent: string;
    indexPageInfo?: PageInfo;
    error?: string;
}>();

const searchQuery = ref('');
const showAllPages = ref(false);

function performSearch() {
    if (searchQuery.value.trim()) {
        // Emit event to open search widget with pre-filled query
        window.dispatchEvent(
            new CustomEvent('open-search-widget', {
                detail: { query: searchQuery.value },
            }),
        );
    } else {
        // Open search widget without query
        window.dispatchEvent(new CustomEvent('open-search-widget'));
    }
}

function formatDate(timestamp: number) {
    return new Date(timestamp * 1000).toLocaleDateString();
}

function getPageTitle(page: Page): string {
    return page.title || page.id.split(':').pop() || page.id;
}
</script>

<template>
    <Head title="Wiki Home" />

    <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="mb-8">
                <h1 class="mb-4 text-3xl font-bold text-gray-900">Wiki</h1>

                <!-- Error Alert -->
                <div v-if="error" class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4">
                    <AlertCircle class="h-5 w-5 text-red-500" />
                    <span class="text-red-700">{{ error }}</span>
                </div>

                <!-- Search Bar -->
                <div class="mb-6 flex gap-2">
                    <Input v-model="searchQuery" placeholder="Search wiki pages..." class="flex-1" @keyup.enter="performSearch" />
                    <Button @click="performSearch" class="flex items-center gap-2">
                        <Search class="h-4 w-4" />
                        Search
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Recent Changes -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5" />
                            Recent Changes
                        </CardTitle>
                        <CardDescription>Latest updates to the wiki</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentChanges.length === 0" class="py-4 text-center text-gray-500">No recent changes available</div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="change in recentChanges.slice(0, 10)"
                                :key="`${change.id || 'unknown'}-${change.revision || 0}`"
                                class="flex items-start justify-between border-b pb-2 last:border-b-0"
                            >
                                <div class="flex-1">
                                    <a
                                        v-if="change.id"
                                        :href="route('wiki.show', { page: change.id })"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {{ change.id.split(':').pop() || change.id }}
                                    </a>
                                    <span v-else class="font-medium text-gray-500"> Unknown page </span>
                                    <div class="text-sm text-gray-500">
                                        by {{ change.author || 'Unknown' }}
                                        <span v-if="change.summary" class="ml-1">- {{ change.summary }}</span>
                                    </div>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <div>{{ change.revision ? formatDate(change.revision) : 'Unknown date' }}</div>
                                    <div v-if="change.sizechange" class="text-xs" :class="change.sizechange > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ change.sizechange > 0 ? '+' : '' }}{{ change.sizechange }} bytes
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Main Wiki Index Page Content -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Wiki Index
                        </CardTitle>
                        <CardDescription v-if="indexPageInfo">
                            Last updated {{ formatDate(indexPageInfo.lastModified) }} by {{ indexPageInfo.author }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="indexPageContent" class="prose prose-slate max-w-none" v-html="indexPageContent"></div>
                        <div v-else class="py-8 text-center text-gray-500">
                            <FileText class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                            <h3 class="mb-2 text-lg font-medium text-gray-900">No index page found</h3>
                            <p class="text-gray-600">The wiki index page could not be loaded. Check if a "start" page exists.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8">
                <h2 class="mb-4 text-xl font-semibold">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Button
                        as="a"
                        :href="route('wiki.show', { page: 'start' })"
                        variant="outline"
                        class="flex h-16 flex-col items-center justify-center"
                    >
                        <FileText class="mb-1 h-6 w-6" />
                        Start Page
                    </Button>
                    <Button
                        @click="() => window.dispatchEvent(new CustomEvent('open-search-widget'))"
                        variant="outline"
                        class="flex h-16 flex-col items-center justify-center"
                    >
                        <Search class="mb-1 h-6 w-6" />
                        Advanced Search
                    </Button>
                    <Button @click="showAllPages = !showAllPages" variant="outline" class="flex h-16 flex-col items-center justify-center">
                        <FileText class="mb-1 h-6 w-6" />
                        All Pages ({{ pages.length }})
                    </Button>
                    <Button as="a" :href="route('wiki.attachments')" variant="outline" class="flex h-16 flex-col items-center justify-center">
                        <FileText class="mb-1 h-6 w-6" />
                        Attachments
                    </Button>
                </div>
            </div>

            <!-- All Pages Modal/Expandable Section -->
            <Card v-if="showAllPages" class="mt-6">
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            All Pages ({{ pages.length }})
                        </span>
                        <Button @click="showAllPages = false" variant="ghost" size="sm"> × </Button>
                    </CardTitle>
                    <CardDescription>Browse all available wiki pages</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="pages.length === 0" class="py-4 text-center text-gray-500">No pages available</div>
                    <div v-else class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                        <a
                            v-for="page in pages"
                            :key="page.id"
                            :href="route('wiki.show', { page: page.id })"
                            class="block rounded border p-3 transition-colors hover:bg-gray-50"
                        >
                            <div class="mb-1 flex items-center justify-between">
                                <span class="font-medium text-blue-600 hover:text-blue-800">
                                    {{ getPageTitle(page) }}
                                </span>
                                <span class="text-sm text-gray-500"> {{ Math.round(page.size / 1024) }}KB </span>
                            </div>
                            <div class="truncate text-sm text-gray-500">
                                {{ page.id }}
                            </div>
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
