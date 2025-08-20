<template>
    <div class="relative">
        <button 
            class="h-9 w-9 rounded-md bg-blue-500 text-white hover:bg-blue-600 flex items-center justify-center"
            @click="openSearch"
            title="Search (Cmd+K)"
        >
            🔍
        </button>
        
        <div 
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50"
            @click="closeSearch"
        >
            <div 
                class="mt-20 w-full max-w-lg mx-4 bg-white rounded-lg shadow-xl p-4"
                @click.stop
            >
                <h3 class="text-lg font-semibold mb-4">Search Wiki</h3>
                <input 
                    v-model="query"
                    type="text"
                    placeholder="Type to search..."
                    class="w-full p-2 border rounded-md"
                    @input="search"
                />
                <div v-if="results.length > 0" class="mt-4 space-y-2">
                    <div 
                        v-for="result in results" 
                        :key="result.id"
                        class="p-2 border rounded hover:bg-gray-50 cursor-pointer"
                        @click="selectResult(result)"
                    >
                        <div class="font-medium">{{ result.title }}</div>
                        <div class="text-sm text-gray-600">{{ result.namespace }}</div>
                    </div>
                </div>
                <button 
                    @click="closeSearch"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700"
                >
                    ✕
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export default {
    setup() {
        const isOpen = ref(false)
        const query = ref('')
        const results = ref([])

        const openSearch = () => {
            isOpen.value = true
        }

        const closeSearch = () => {
            isOpen.value = false
            query.value = ''
            results.value = []
        }

        const search = async () => {
            if (!query.value.trim()) {
                results.value = []
                return
            }

            try {
                const response = await fetch(`/api/wiki/search?q=${encodeURIComponent(query.value)}&limit=5`)
                const data = await response.json()
                results.value = data.hits || []
            } catch (error) {
                console.error('Search failed:', error)
                results.value = []
            }
        }

        const selectResult = (result) => {
            router.visit(result.url)
            closeSearch()
        }

        return {
            isOpen,
            query,
            results,
            openSearch,
            closeSearch,
            search,
            selectResult
        }
    }
}
</script>