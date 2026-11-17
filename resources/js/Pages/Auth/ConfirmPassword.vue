<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
    password: '',
})

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    })
}
</script>

<template>
    <GuestLayout
        title="Potwierdź hasło"
        description="To zabezpieczony obszar aplikacji. Potwierdź hasło, zanim przejdziesz dalej."
    >
        <Head title="Potwierdź hasło" />

        <form class="grid gap-4" @submit.prevent="submit">
            <FormField id="password" label="Hasło" :error="form.errors.password">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="password"
                        v-model="form.password"
                        required
                        autofocus
                        autocomplete="current-password"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <Button type="submit" size="lg" class="w-full" :disabled="form.processing">
                Potwierdź
            </Button>
        </form>
    </GuestLayout>
</template>
