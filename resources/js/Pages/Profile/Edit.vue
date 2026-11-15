<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import { Head } from '@inertiajs/vue3'
import PageHeader from '@/Components/Common/PageHeader.vue'
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <div class="mx-auto grid max-w-2xl gap-6">
            <PageHeader title="Profil" />
            <UpdateProfileInformationForm />
            <UpdatePasswordForm />
            <DeleteUserForm />
        </div>
    </AuthenticatedLayout>
</template>
