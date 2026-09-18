<script setup lang="ts">
import { CopilotKitProvider, HttpAgent } from '@copilotkit/vue/v2';
import type { Interrupt, Message } from '@copilotkit/vue/v2';
import '@copilotkit/vue/styles.css';
import AgentChatView from '@/components/chat/AgentChatView.vue';

type Props = {
    // The Laravel endpoint that streams the AG-UI events of the agent.
    url: string;
    threadId: string;
    messages: Message[];
    // The approvals a suspended run was waiting for when the page was loaded.
    pendingApprovals: Interrupt[];
};

const props = defineProps<Props>();

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

// The chat talks straight to the Laravel controller that streams AG-UI events: no Copilot runtime in between.
const agent = new HttpAgent({
    url: props.url,
    threadId: props.threadId,
    // The agent clones its messages with structuredClone(), which rejects Vue's reactive proxies.
    initialMessages: JSON.parse(JSON.stringify(props.messages)),
    headers: { 'X-XSRF-TOKEN': xsrfToken() },
});

// As after a live interrupt, the agent refuses a new message until the approvals are answered.
agent.pendingInterrupts = JSON.parse(JSON.stringify(props.pendingApprovals));

const agents = { default: agent };
</script>

<template>
    <CopilotKitProvider :self-managed-agents="agents" :enable-inspector="false">
        <AgentChatView
            :thread-id="threadId"
            :pending-approvals="pendingApprovals"
        />
    </CopilotKitProvider>
</template>
