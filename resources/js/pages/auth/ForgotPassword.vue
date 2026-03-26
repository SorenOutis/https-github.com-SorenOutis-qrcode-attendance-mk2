<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { onMounted, useTemplateRef } from 'vue';
import gsap from 'gsap';

defineProps<{
    status?: string;
}>();

const formRef = useTemplateRef<HTMLElement>('formRef');

onMounted(() => {
    gsap.from(formRef.value!.querySelectorAll('.animate-item'), {
        y: 20,
        opacity: 0,
        duration: 0.8,
        stagger: 0.12,
        ease: 'power4.out',
        delay: 0.8, // Start after layout animation
    });
});
</script>

<template>
    <AuthLayout
        title="Reset password"
        description="Enter your email to receive a password reset link"
    >
        <Head title="Forgot password" />

        <div
            v-if="status"
            class="mb-6 rounded-xl bg-emerald-50/50 p-3 text-center text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200/50 backdrop-blur-sm animate-item dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20"
        >
            {{ status }}
        </div>

        <div class="space-y-6" ref="formRef">
            <Form v-bind="email.form()" v-slot="{ errors, processing }" class="space-y-5">
                <div class="grid gap-2 animate-item">
                    <Label for="email" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="name@company.com"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="animate-item pt-2">
                    <Button
                        class="h-11 w-full rounded-xl bg-zinc-900 font-semibold text-white shadow-lg shadow-zinc-900/10 transition-all hover:bg-zinc-800 hover:shadow-xl active:scale-[0.98] dark:bg-zinc-100 dark:text-zinc-900 dark:shadow-none dark:hover:bg-zinc-200"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        Send reset link
                    </Button>
                </div>
            </Form>

            <div class="text-center animate-item text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Remember your password?
                <TextLink :href="login()" class="font-bold text-zinc-900 underline-offset-8 transition-all hover:underline dark:text-zinc-100">Sign in</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
