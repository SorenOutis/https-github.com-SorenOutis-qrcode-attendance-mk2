<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';

type Subject = {
    id: number;
    name: string;
};

const props = defineProps<{
    subjects: Subject[];
}>();

const selectedSubjectId = ref<string>('');
const selectedDate = ref<string>(new Date().toISOString().split('T')[0]);

function fetchRoster() {
    if (!selectedSubjectId.value || !selectedDate.value) return;
    router.get(`/manage-attendance/${selectedSubjectId.value}/${selectedDate.value}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Manage Attendance', href: '/manage-attendance' }]">
        <Head title="Manage Attendance" />

        <div class="flex h-full flex-col gap-8 p-6 lg:p-10 max-w-3xl mx-auto w-full">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold tracking-tight">Manage Attendance</h1>
                <p class="text-muted-foreground">
                    Select a subject and a date to view the attendance roster and mark attendance manually.
                </p>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm flex flex-col gap-6">
                <div class="flex flex-col gap-3">
                    <Label>Subject</Label>
                    <Select v-model="selectedSubjectId">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a subject..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="subject in subjects" :key="subject.id" :value="subject.id.toString()">
                                {{ subject.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex flex-col gap-3">
                    <Label>Date</Label>
                    <Input type="date" v-model="selectedDate" />
                </div>

                <Button @click="fetchRoster" :disabled="!selectedSubjectId || !selectedDate" class="w-full mt-2">
                    View Class Roster
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
