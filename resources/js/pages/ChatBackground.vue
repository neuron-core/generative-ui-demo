<script setup lang="ts">
import type { Message } from '@copilotkit/vue/v2';
import { Head, router } from '@inertiajs/vue3';
import { SquarePen } from '@lucide/vue';
import AgentChat from '@/components/chat/AgentChat.vue';
import { Button } from '@/components/ui/button';
import { chatBackground } from '@/routes';
import { store, stream } from '@/routes/chat-background';

type Props = {
    threadId: string;
    messages: Message[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Chat (background job)',
                href: chatBackground(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Chat (background job)" />

    <div class="relative flex h-[calc(100svh-5rem)] min-h-0 flex-col">
        <Button
            variant="outline"
            size="sm"
            class="absolute top-2 right-4 z-10"
            @click="router.post(store.url())"
        >
            <SquarePen />
            New chat
        </Button>

        <AgentChat
            :key="threadId"
            :url="stream.url()"
            :thread-id="threadId"
            :messages="messages"
        />
    </div>
</template>
