<script setup>
import FormField from '@/Components/Common/FormField.vue';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle as="h2">Dane profilu</CardTitle>
            <CardDescription>
                Zaktualizuj imię i adres e-mail powiązane z kontem.
            </CardDescription>
        </CardHeader>

        <CardContent>
            <form
                class="grid gap-4"
                @submit.prevent="form.patch(route('profile.update'))"
            >
                <FormField id="name" label="Imię" :error="form.errors.name">
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            type="text"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                            :aria-invalid="invalid || undefined"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField
                    id="email"
                    label="Adres e-mail"
                    :error="form.errors.email"
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            type="email"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :aria-invalid="invalid || undefined"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        Zapisz
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Zapisano.
                        </p>
                    </Transition>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
