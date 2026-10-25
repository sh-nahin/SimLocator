"use client";

import { useState, type FormEvent } from "react";
import { Alert, Button, Card, Form, Input, Label, Table, TextField } from "@heroui/react";
import { api, ApiError } from "@/lib/api";
import { OperatorChip } from "@/components/OperatorChip";

type Row = { id: number; name: string; email: string; phone: string; operator: string; district: string; area: string; created_at: string };
type Data = { total: number; by_operator: { operator: string; total: number }[]; registrations: Row[] };

export default function AdminPage() {
  const [key, setKey] = useState("");
  const [data, setData] = useState<Data | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");

  async function load(e?: FormEvent) {
    e?.preventDefault();
    setLoading(true);
    setError("");
    try {
      setData(await api<Data>("/api/admin/registrations", { headers: { "X-Admin-Key": key } }));
    } catch (err) {
      setData(null);
      setError((err as ApiError).message);
    } finally {
      setLoading(false);
    }
  }

  if (!data) {
    return (
      <Card className="mx-auto mt-10 max-w-md">
        <Card.Header>
          <Card.Title>Admin</Card.Title>
          <Card.Description>Enter the ADMIN_KEY </Card.Description>
        </Card.Header>
        <Card.Content>
          {error && (
            <Alert status="danger" className="mb-4">
              <Alert.Indicator />
              <Alert.Content><Alert.Title>{error}</Alert.Title></Alert.Content>
            </Alert>
          )}
          <Form onSubmit={load} className="flex flex-col gap-4">
            <TextField isRequired type="password" name="key" value={key} onChange={setKey} fullWidth>
              <Label>Admin key</Label>
              <Input />
            </TextField>
            <Button type="submit" variant="primary" fullWidth isPending={loading}>View registrations</Button>
          </Form>
        </Card.Content>
      </Card>
    );
  }

  const q = search.trim().toLowerCase();
  const rows = q
    ? data.registrations.filter((r) => [r.name, r.email, r.phone, r.operator, r.district, r.area].some((v) => v.toLowerCase().includes(q)))
    : data.registrations;

  return (
    <div className="mt-4 flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Registrations</h1>
          <p className="text-muted">Everyone who has registered, with their detected operator and location.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onPress={() => load()} isPending={loading}>Refresh</Button>
          <Button variant="ghost" onPress={() => { setData(null); setKey(""); }}>Log out</Button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
        <Card><Card.Content><p className="text-sm text-muted">Total</p><p className="text-3xl font-bold">{data.total}</p></Card.Content></Card>
        {data.by_operator.map((o) => (
          <Card key={o.operator}>
            <Card.Content>
              <OperatorChip name={o.operator} />
              <p className="mt-2 text-3xl font-bold">{o.total}</p>
            </Card.Content>
          </Card>
        ))}
      </div>

      <TextField value={search} onChange={setSearch} aria-label="Search registrations" fullWidth>
        <Input placeholder="Search by name, phone, email, operator or place…" />
      </TextField>

      <Table>
        <Table.ScrollContainer>
          <Table.Content aria-label="Registrations" className="min-w-[760px]">
            <Table.Header>
              <Table.Column isRowHeader>Name</Table.Column>
              <Table.Column>Phone</Table.Column>
              <Table.Column>Email</Table.Column>
              <Table.Column>Operator</Table.Column>
              <Table.Column>Location</Table.Column>
              <Table.Column>Registered</Table.Column>
            </Table.Header>
            <Table.Body renderEmptyState={() => <p className="py-8 text-center text-muted">No registrations found.</p>}>
              {rows.map((r) => (
                <Table.Row key={r.id} id={r.id}>
                  <Table.Cell>{r.name}</Table.Cell>
                  <Table.Cell>{r.phone}</Table.Cell>
                  <Table.Cell>{r.email}</Table.Cell>
                  <Table.Cell><OperatorChip name={r.operator} /></Table.Cell>
                  <Table.Cell>{r.area}, {r.district}</Table.Cell>
                  <Table.Cell>{r.created_at}</Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Content>
        </Table.ScrollContainer>
      </Table>
      {data.total > data.registrations.length && (
        <p className="text-sm text-muted">Showing the latest {data.registrations.length} of {data.total}.</p>
      )}
    </div>
  );
}
