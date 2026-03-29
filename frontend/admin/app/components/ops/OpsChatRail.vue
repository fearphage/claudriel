<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { useChatRail } from '~/composables/useChatRail'

const { t } = useLanguage()
const {
  messages,
  sessionId,
  sending,
  error,
  continuation,
  sendMessage,
  continueSession,
  clearConversation,
} = useChatRail()

const input = ref('')

async function onSubmit() {
  const text = input.value.trim()
  if (!text) {
    return
  }
  input.value = ''
  await sendMessage(text)
}
</script>

<template>
  <div class="ops-chat-rail">
    <div class="ops-chat-head">
      <h2 class="ops-chat-title">{{ t('ops_chat_title') }}</h2>
      <div class="ops-chat-head-actions">
        <button type="button" class="btn btn-sm" @click="clearConversation">
          {{ t('ops_chat_new') }}
        </button>
      </div>
    </div>

    <p v-if="sessionId" class="ops-chat-meta">
      {{ t('ops_chat_session') }}: <code>{{ sessionId.slice(0, 8) }}…</code>
    </p>

    <div v-if="error" class="error ops-chat-error">{{ error }}</div>

    <div class="ops-chat-messages">
      <div v-if="messages.length === 0" class="ops-chat-empty">
        {{ t('ops_chat_empty') }}
      </div>
      <div
        v-for="(m, i) in messages"
        :key="i"
        class="ops-chat-msg"
        :class="`ops-chat-msg--${m.role}`"
      >
        <span class="ops-chat-role">{{ m.role }}</span>
        <pre class="ops-chat-text">{{ m.content }}</pre>
      </div>
    </div>

    <div v-if="continuation" class="ops-chat-continue">
      <p>{{ continuation.message }}</p>
      <button type="button" class="btn btn-primary btn-sm" @click="continueSession">
        {{ t('ops_chat_continue') }}
      </button>
    </div>

    <form class="ops-chat-form" @submit.prevent="onSubmit">
      <textarea
        v-model="input"
        class="field-input ops-chat-input"
        rows="3"
        :placeholder="t('ops_chat_placeholder')"
        :disabled="sending"
      />
      <button type="submit" class="btn btn-primary" :disabled="sending">
        {{ sending ? t('loading') : t('ops_chat_send') }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.ops-chat-rail {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}
.ops-chat-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}
.ops-chat-title {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 600;
}
.ops-chat-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 8px;
}
.ops-chat-meta code {
  font-size: 11px;
}
.ops-chat-error {
  margin-bottom: 8px;
  font-size: 13px;
}
.ops-chat-messages {
  flex: 1;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 10px;
  margin-bottom: 12px;
  background: var(--bg-deep);
  min-height: 160px;
}
.ops-chat-empty {
  color: var(--text-muted);
  font-size: 13px;
  padding: 12px;
}
.ops-chat-msg {
  margin-bottom: 12px;
}
.ops-chat-role {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 4px;
}
.ops-chat-text {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: var(--font-body);
  font-size: 13px;
  line-height: 1.45;
  color: var(--text-primary);
}
.ops-chat-msg--user .ops-chat-text {
  color: var(--accent-teal);
}
.ops-chat-continue {
  padding: 10px;
  border: 1px solid var(--border-emphasis);
  border-radius: var(--radius-md);
  margin-bottom: 10px;
  font-size: 13px;
  color: var(--text-secondary);
}
.ops-chat-continue p {
  margin-bottom: 8px;
}
.ops-chat-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ops-chat-input {
  resize: vertical;
  min-height: 72px;
}
</style>
