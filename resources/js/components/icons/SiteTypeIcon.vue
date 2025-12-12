<script setup lang="ts">
import HtmlIcon from './HtmlIcon.vue';
import LaravelIcon from './LaravelIcon.vue';
import NextJsIcon from './NextJsIcon.vue';
import NuxtJsIcon from './NuxtJsIcon.vue';
import OtherIcon from './OtherIcon.vue';
import PhpIcon from './PhpIcon.vue';
import PhpMyAdminIcon from './PhpMyAdminIcon.vue';
import StatamicIcon from './StatamicIcon.vue';
import SymfonyIcon from './SymfonyIcon.vue';
import WordpressIcon from './WordpressIcon.vue';

interface Props {
    type: string;
}

defineProps<Props>();
</script>

<template>
    <LaravelIcon v-if="type === 'laravel'" />
    <SymfonyIcon v-else-if="type === 'symfony'" />
    <StatamicIcon v-else-if="type === 'statamic'" />
    <WordpressIcon v-else-if="type === 'wordpress'" />
    <PhpMyAdminIcon v-else-if="type === 'phpmyadmin'" />
    <PhpIcon v-else-if="type === 'php'" />
    <NextJsIcon v-else-if="type === 'nextjs'" />
    <NuxtJsIcon v-else-if="type === 'nuxtjs'" />
    <HtmlIcon v-else-if="type === 'html'" />
    <OtherIcon v-else />
</template>
