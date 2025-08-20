<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/vue3';
import { History, ArrowLeft, Clock, User, FileText, RotateCcw, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout
});

interface HistoryEntry {
    version: number;
    author: string;
    timestamp: number;
    summary: string;
    ip?: string;
    sizechange?: number;
    size?: number;
}

const props = defineProps<{
    page: string;
    versions: HistoryEntry[];
    error?: string;
    pageInfo?: {
        name: string;
        lastModified: number;
        author: string;
        version: number;
        size: number;
    };
}>();

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

function getPageTitle(page: string, pageInfo?: { name: string }): string {
    if (pageInfo?.name) {
        return pageInfo.name;
    }
    
    const parts = page.split(':');
    const lastPart = parts[parts.length - 1];
    
    if (lastPart === 'index' && parts.length > 1) {
        const previousPart = parts[parts.length - 2];
        return previousPart.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    return lastPart.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function goBack() {
    router.visit(route('wiki.show', { page: props.page }));
}

function viewRevision(version: number) {
    // This would typically open a specific revision of the page
    // For now, we'll just redirect to the current page with a revision parameter
    window.open(`https://wiki.eurofurence.org/doku.php?id=${props.page}&rev=${version}`, '_blank');
}

function compareRevisions(from: number, to: number) {
    // This would typically show a diff between two revisions
    window.open(`https://wiki.eurofurence.org/doku.php?id=${props.page}&do=diff&rev2=${to}&rev=${from}`, '_blank');
}
</script>

<template>
    <Head :title="`History: ${getPageTitle(props.page, props.pageInfo)} - Wiki`" />

    <div class="w-full py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-3">
                            <History class="h-8 w-8" />
                            Page History
                        </h1>
                        <p class="text-lg text-gray-600 dark:text-gray-300">
                            {{ getPageTitle(props.page, props.pageInfo) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ props.page }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Back to Page button -->
                        <Button 
                            @click="goBack"
                            variant="outline" 
                            size="sm"
                            class="flex items-center gap-2"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to Page
                        </Button>

                        <!-- Open in DokuWiki button -->
                        <Button 
                            as="a" 
                            :href="`https://wiki.eurofurence.org/doku.php?id=${props.page}&do=revisions`"
                            target="_blank"
                            variant="outline" 
                            size="sm"
                            class="flex items-center gap-2"
                        >
                            <ExternalLink class="h-4 w-4" />
                            Open in DokuWiki
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Error Alert -->
            <div v-if="props.error" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md flex items-center gap-2">
                <FileText class="h-5 w-5 text-red-500 dark:text-red-400" />
                <span class="text-red-700 dark:text-red-300">{{ props.error }}</span>
            </div>

            <!-- History Content -->
            <Card v-if="props.versions && props.versions.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Revision History
                    </CardTitle>
                    <CardDescription>
                        {{ props.versions.length }} revision{{ props.versions.length !== 1 ? 's' : '' }} found
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div 
                            v-for="(entry, index) in props.versions" 
                            :key="entry.version"
                            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Version {{ entry.version }}
                                        </span>
                                        <span v-if="index === 0" class="bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300 text-xs px-2 py-1 rounded-full">
                                            Current
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <User class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                            <div>
                                                <div class="text-gray-600 dark:text-gray-400">Author</div>
                                                <div class="font-medium">{{ entry.author || 'Unknown' }}</div>
                                                <div v-if="entry.ip" class="text-xs text-gray-500 dark:text-gray-400">{{ entry.ip }}</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Clock class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                            <div>
                                                <div class="text-gray-600 dark:text-gray-400">Modified</div>
                                                <div class="font-medium">{{ formatDate(entry.timestamp) }}</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                            <div>
                                                <div class="text-gray-600 dark:text-gray-400">Size</div>
                                                <div class="font-medium flex items-center gap-2">
                                                    {{ entry.size ? formatFileSize(entry.size) : 'Unknown' }}
                                                    <span 
                                                        v-if="entry.sizechange" 
                                                        class="text-xs px-1 py-0.5 rounded"
                                                        :class="entry.sizechange > 0 ? 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30' : 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30'"
                                                    >
                                                        {{ entry.sizechange > 0 ? '+' : '' }}{{ entry.sizechange }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="entry.summary" class="mt-3 p-2 bg-gray-50 dark:bg-gray-800 rounded text-sm">
                                        <strong>Summary:</strong> {{ entry.summary }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 ml-4">
                                    <Button
                                        @click="viewRevision(entry.version)"
                                        variant="outline"
                                        size="sm"
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <FileText class="h-3 w-3" />
                                        View
                                    </Button>
                                    
                                    <Button
                                        v-if="index < props.versions.length - 1"
                                        @click="compareRevisions(props.versions[index + 1].version, entry.version)"
                                        variant="outline"
                                        size="sm"
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <RotateCcw class="h-3 w-3" />
                                        Diff
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- No History State -->
            <Card v-else-if="!props.error">
                <CardContent class="text-center py-12">
                    <History class="h-16 w-16 text-gray-400 dark:text-gray-500 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No History Available</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        No revision history could be found for this page.
                    </p>
                    <Button @click="goBack" variant="outline">
                        Back to Page
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>