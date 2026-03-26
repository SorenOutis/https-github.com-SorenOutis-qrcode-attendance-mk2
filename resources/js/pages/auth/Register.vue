<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { onMounted, useTemplateRef } from 'vue';
import gsap from 'gsap';

const formRef = useTemplateRef<HTMLElement>('formRef');

onMounted(() => {
    gsap.from(formRef.value!.querySelectorAll('.animate-item'), {
        y: 20,
        opacity: 0,
        duration: 0.8,
        stagger: 0.08,
        ease: 'power4.out',
        delay: 0.8, // Start after layout animation
    });
});
</script>

<template>
    <AuthBase
        title="Create account"
        description="Join us today and start managing your attendance"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="space-y-4"
            ref="formRef"
        >
            <div class="grid gap-3.5">
                <div class="grid gap-2 animate-item">
                    <Label for="name" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Full Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="John Doe"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2 animate-item">
                    <Label for="email" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="name@company.com"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2 animate-item">
                    <Label for="password" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Password</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="••••••••"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2 animate-item">
                    <Label for="password_confirmation" class="text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">Confirm Password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="••••••••"
                        class="h-11 rounded-xl border-zinc-200 bg-white/50 focus-visible:ring-zinc-900 dark:border-zinc-800 dark:bg-zinc-950/50 dark:focus-visible:ring-zinc-100"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full animate-item mt-2 rounded-xl bg-zinc-900 font-semibold text-white shadow-lg shadow-zinc-900/10 transition-all hover:bg-zinc-800 hover:shadow-xl active:scale-[0.98] dark:bg-zinc-100 dark:text-zinc-900 dark:shadow-none dark:hover:bg-zinc-200"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    Create account
                </Button>
            </div>

            <div class="text-center animate-item pt-2 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="font-bold text-zinc-900 underline-offset-8 transition-all hover:underline dark:text-zinc-100"
                    :tabindex="6"
                    >Sign in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
