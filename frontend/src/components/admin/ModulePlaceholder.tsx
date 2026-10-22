type ModulePlaceholderProps = {
  title: string;
  description: string;
};

export function ModulePlaceholder({ title, description }: ModulePlaceholderProps) {
  return (
    <section className="max-w-3xl">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
      <p className="mt-3 max-w-2xl text-sm leading-6 text-muted">{description}</p>
      <div className="mt-6 rounded-2xl border border-border bg-surface p-6">
        <p className="text-sm leading-6 text-foreground">
          Módulo preparado para integración CRUD. La gestión visual de este recurso se habilitará
          en el siguiente incremento, sobre las rutas API administrativas ya existentes.
        </p>
      </div>
    </section>
  );
}
