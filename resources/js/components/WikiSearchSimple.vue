<template>
    <div class="relative">
        <button
            class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-500 text-white hover:bg-blue-600"
            @click="openSearch"
            title="Search (Cmd+K)"
        >
            🔍
        </button>

        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50" @click="closeSearch">
            <div class="mx-4 mt-20 w-full max-w-lg rounded-lg bg-white p-4 shadow-xl" @click.stop>
                <h3 class="mb-4 text-lg font-semibold">Search Wiki</h3>
                <input v-model="query" type="text" placeholder="Type to search..." class="w-full rounded-md border p-2" @input="search" />
                <div v-if="results.length > 0" class="mt-4 space-y-2">
                    <div
                        v-for="result in results"
                        :key="result.id"
                        class="cursor-pointer rounded border p-2 hover:bg-gray-50"
                        @click="selectResult(result)"
                    >
                        <div class="font-medium">{{ result.title }}</div>
                        <div class="text-sm text-gray-600">{{ result.namespace }}</div>
                    </div>
                </div>
                <button @click="closeSearch" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">✕</button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const isOpen = ref(false);
const query = ref('');
const results = ref<Array<{ id: string; title: string; namespace: string; url: string }>>([]);

const openSearch = () => {
    isOpen.value = true;
};

const closeSearch = () => {
    isOpen.value = false;
    query.value = '';
    results.value = [];
};

const search = async () => {
    if (!query.value.trim()) {
        results.value = [];
        return;
    }

    try {
        const response = await fetch(`/api/wiki/search?q=${encodeURIComponent(query.value)}&limit=5`);
        const data = await response.json();
        results.value = data.hits || [];
    } catch (error) {
        console.error('Search failed:', error);
        results.value = [];
    }
};

const selectResult = (result: { url: string }) => {
    router.visit(result.url);
    closeSearch();
};
</script>
