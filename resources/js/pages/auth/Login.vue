<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { onMounted, useTemplateRef } from 'vue';
import gsap from 'gsap';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
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
    <AuthBase
        title="Welcome back"
        description="Enter your credentials to access your account"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-6 rounded-xl bg-emerald-50/50 p-3 text-center text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200/50 backdrop-blur-sm animate-item dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="space-y-6"
            ref="formRef"
        >
            <div class="grid gap-5">
                <div class="grid gap-2 animate-item">
                    <Label for="email" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="name@company.com"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2 animate-item">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-xs font-semibold text-zinc-900 transition-colors hover:text-zinc-600 dark:text-zinc-300 dark:hover:text-zinc-100"
                            :tabindex="5"
                        >
                            Forgot?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between animate-item">
                    <Label for="remember" class="group flex cursor-pointer items-center space-x-2.5">
                        <Checkbox id="remember" name="remember" :tabindex="3" class="rounded-[4px] border-zinc-300 data-[state=checked]:bg-zinc-900 data-[state=checked]:border-zinc-900 dark:border-zinc-700 dark:data-[state=checked]:bg-zinc-100 dark:data-[state=checked]:border-zinc-100" />
                        <span class="text-sm font-medium text-zinc-600 transition-colors group-hover:text-zinc-900 dark:text-zinc-400 dark:group-hover:text-zinc-200">Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full animate-item rounded-xl bg-zinc-900 font-semibold text-white shadow-lg shadow-zinc-900/10 transition-all hover:bg-zinc-800 hover:shadow-xl active:scale-[0.98] dark:bg-zinc-100 dark:text-zinc-900 dark:shadow-none dark:hover:bg-zinc-200"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    Sign in
                </Button>
            </div>

            <div
                class="text-center text-sm font-medium text-zinc-500 animate-item dark:text-zinc-400"
                v-if="canRegister"
            >
                New here?
                <TextLink :href="register()" class="font-bold text-zinc-900 underline-offset-8 transition-all hover:underline dark:text-zinc-100" :tabindex="5">Create account</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
