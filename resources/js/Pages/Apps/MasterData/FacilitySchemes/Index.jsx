import AppLayout from "@/Layouts/AppLayout";
import Button from "@/Components/Button";
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import Pagination from "@/Components/Pagination";
import Table from "@/Components/Table";
import TextInput from "@/Components/TextInput";
import { Head, useForm } from "@inertiajs/react";
import {
    IconCirclePlus,
    IconDatabaseOff,
    IconPencilCog,
    IconTrash,
    IconX,
} from "@tabler/icons-react";
import { useEffect, useState } from "react";

const initialForm = {
    code: "",
    name: "",
    description: "",
    is_active: true,
    is_restricted: false,
    requires_tracking: true,
    requires_reporting: false,
    requires_approval: false,
    requires_reference_no: false,
};

export default function Index({ data }) {
    const [editing, setEditing] = useState(null);
    const [showForm, setShowForm] = useState(false);
    const form = useForm(initialForm);

    useEffect(() => {
        if (editing) {
            setShowForm(true);
            form.setData({
                code: editing.code ?? "",
                name: editing.name ?? "",
                description: editing.description ?? "",
                is_active: !!editing.is_active,
                is_restricted: !!editing.is_restricted,
                requires_tracking: !!editing.requires_tracking,
                requires_reporting: !!editing.requires_reporting,
                requires_approval: !!editing.requires_approval,
                requires_reference_no: !!editing.requires_reference_no,
            });
        } else {
            form.setData(initialForm);
        }
    }, [editing]);

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            form.put(
                route("apps.master-data.facility-schemes.update", editing.id),
                {
                    preserveScroll: true,
                    onSuccess: () => setEditing(null),
                },
            );
            return;
        }
        form.post(route("apps.master-data.facility-schemes.store"), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Master Fasilitas" />
            <div className="mb-5 flex items-center justify-end">
                {!showForm ? (
                    <Button
                        type="button"
                        onClick={() => {
                            setEditing(null);
                            setShowForm(true);
                        }}
                        icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                        variant="gray"
                        label="Tambah"
                    />
                ) : (
                    <Button
                        type="button"
                        onClick={() => {
                            setEditing(null);
                            setShowForm(false);
                        }}
                        icon={<IconX size={20} strokeWidth={1.5} />}
                        variant="gray"
                        label="Tutup Form"
                    />
                )}
            </div>
            <div className="space-y-5">
                {showForm && (
                    <Table.Card
                        title={editing ? "Edit Fasilitas" : "Tambah Fasilitas"}
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <TextInput
                                        value={form.data.code}
                                        onChange={(e) =>
                                            form.setData("code", e.target.value)
                                        }
                                        placeholder="Kode"
                                        className="w-full"
                                    />
                                    <InputError message={form.errors.code} />
                                </div>
                                <div>
                                    <TextInput
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData("name", e.target.value)
                                        }
                                        placeholder="Nama fasilitas"
                                        className="w-full"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                            </div>
                            <div>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            "description",
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    rows={3}
                                    placeholder="Deskripsi"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                {[
                                    ["is_active", "Aktif"],
                                    ["is_restricted", "Restricted"],
                                    ["requires_tracking", "Perlu Tracking"],
                                    ["requires_reporting", "Perlu Reporting"],
                                    ["requires_approval", "Perlu Approval"],
                                    [
                                        "requires_reference_no",
                                        "No Referensi Wajib",
                                    ],
                                ].map(([key, label]) => (
                                    <label
                                        key={key}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={form.data[key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    key,
                                                    e.target.checked,
                                                )
                                            }
                                        />{" "}
                                        {label}
                                    </label>
                                ))}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    variant="blue"
                                    label={editing ? "Update" : "Simpan"}
                                />
                                {editing && (
                                    <Button
                                        type="button"
                                        onClick={() => setEditing(null)}
                                        variant="gray"
                                        label="Batal"
                                    />
                                )}
                            </div>
                        </form>
                    </Table.Card>
                )}

                <div>
                    <Table.Card title="Data Fasilitas">
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className="w-10">No</Table.Th>
                                    <Table.Th>Kode</Table.Th>
                                    <Table.Th>Nama</Table.Th>
                                    <Table.Th>Status</Table.Th>
                                    <Table.Th className="w-32"></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {data.data.length ? (
                                    data.data.map((item, i) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-gray-100 dark:hover:bg-gray-900"
                                        >
                                            <Table.Td className="text-center">
                                                {i +
                                                    1 +
                                                    (data.current_page - 1) *
                                                        data.per_page}
                                            </Table.Td>
                                            <Table.Td>{item.code}</Table.Td>
                                            <Table.Td>{item.name}</Table.Td>
                                            <Table.Td>
                                                {item.is_active
                                                    ? "Aktif"
                                                    : "Nonaktif"}
                                            </Table.Td>
                                            <Table.Td>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            setEditing(item)
                                                        }
                                                        icon={
                                                            <IconPencilCog
                                                                size={16}
                                                                strokeWidth={
                                                                    1.5
                                                                }
                                                            />
                                                        }
                                                        variant="orange"
                                                    />
                                                    <Button
                                                        type="delete"
                                                        url={route(
                                                            "apps.master-data.facility-schemes.destroy",
                                                            item.id,
                                                        )}
                                                        icon={
                                                            <IconTrash
                                                                size={16}
                                                                strokeWidth={
                                                                    1.5
                                                                }
                                                            />
                                                        }
                                                        variant="rose"
                                                    />
                                                </div>
                                            </Table.Td>
                                        </tr>
                                    ))
                                ) : (
                                    <Table.Empty
                                        colSpan={5}
                                        message={
                                            <>
                                                <IconDatabaseOff
                                                    size={24}
                                                    strokeWidth={1.5}
                                                    className="mx-auto mb-2 text-gray-500 dark:text-white"
                                                />
                                                <span className="text-gray-500">
                                                    Data fasilitas tidak
                                                    ditemukan.
                                                </span>
                                            </>
                                        }
                                    />
                                )}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                    {data.last_page !== 1 && <Pagination links={data.links} />}
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
