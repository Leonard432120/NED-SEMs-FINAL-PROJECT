#!/usr/bin/env python3
"""Generate complete draw.io ERD from database/ned_sems.sql"""

import re
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SQL_PATH = ROOT / "database" / "ned_sems.sql"
EXISTING_PATH = ROOT / "uml.drawio" / "uml.drawio"
OUTPUT_PATH = ROOT / "uml.drawio" / "uml.drawio"

# Preserve positions from existing diagram (non-REF tables)
LAYOUT = {
    "users": (245, 90),
    "schools": (-50, 130),
    "subjects": (560, -70),
    "students": (-30, 470),
    "exams": (550, 140),
    "questions": (870, 140),
    "exam_subjects": (245, 550),
    "exam_documents": (570, 510),
    "subject_assignments": (560, 750),
    "question_moderation": (-40, 1260),
    "school_reports": (850, 840),
    "results": (-30, 780),
    "comments": (850, 565),
    "ai_analysis": (1090, 325),
    "audit_logs": (320, -140),
    "password_resets": (480, 960),
    "marks": (780, 1030),
    "exam_workflow_logs": (1090, 1270),
    "result_workflow_logs": (190, 1180),
    # New tables – placed near related entities
    "announcements": (320, 320),
    "marking_assignments": (245, 820),
    "profile_change_otps": (480, 1180),
    "student_subjects": (-30, 710),
}

TABLE_STYLE = (
    "shape=table;startSize=30;container=1;collapsible=1;childLayout=tableLayout;"
    "fixedRows=1;rowLines=0;fontStyle=1;align=center;resizeLast=1;html=1;"
    "perimeterSpacing=1;strokeWidth=2;fontSize=14;fontFamily=Times New Roman;"
    "labelPosition=center;verticalLabelPosition=middle;verticalAlign=middle;"
)
ROW_STYLE = (
    "shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;"
    "fillColor=none;collapsible=0;dropTarget=0;points=[[0,0.5],[1,0.5]];"
    "portConstraint=eastwest;top=0;left=0;right=0;bottom=0;perimeterSpacing=1;"
    "strokeWidth=2;fontStyle=1;fontSize=14;fontFamily=Times New Roman;"
    "labelPosition=center;verticalLabelPosition=middle;align=center;verticalAlign=middle;"
)
PK_ROW_STYLE = ROW_STYLE.replace("bottom=0", "bottom=1")
CELL_BASE = (
    "shape=partialRectangle;connectable=0;fillColor=none;top=0;left=0;bottom=0;right=0;"
    "overflow=hidden;whiteSpace=wrap;html=1;perimeterSpacing=1;strokeWidth=2;"
    "fontSize=14;fontFamily=Times New Roman;labelPosition=center;"
    "verticalLabelPosition=middle;align=center;verticalAlign=middle;"
)
EDGE_STYLE = (
    "edgeStyle=orthogonalEdgeStyle;fontSize=14;html=1;endArrow=ERoneToMany;"
    "fontStyle=1;fontFamily=Times New Roman;labelPosition=center;"
    "verticalLabelPosition=middle;align=center;verticalAlign=middle;strokeWidth=2;"
)

FK_COLUMN_MAP = {
    "user_id": "users",
    "target_user_id": "users",
    "teacher_id": "users",
    "moderator_id": "users",
    "created_by": "users",
    "published_by": "users",
    "performed_by": "users",
    "assigned_by": "users",
    "compiled_by": "users",
    "uploaded_by": "users",
    "submitted_by": "users",
    "received_by": "users",
    "school_id": "schools",
    "exam_id": "exams",
    "subject_id": "subjects",
    "student_id": "students",
    "question_id": "questions",
    "result_id": "results",
}


def parse_sql(path: Path):
    text = path.read_text(encoding="utf-8", errors="replace")
    tables = {}

    for m in re.finditer(
        r"CREATE TABLE IF NOT EXISTS `(\w+)` \((.*?)\) ENGINE=",
        text,
        re.DOTALL | re.IGNORECASE,
    ):
        name = m.group(1)
        body = m.group(2)
        columns = []
        pk_cols = set()
        keyed_cols = set()

        pk_match = re.search(r"PRIMARY KEY \(`([^`]+)`\)", body)
        if pk_match:
            pk_cols.add(pk_match.group(1))

        for cm in re.finditer(r"^\s+`(\w+)`\s+", body, re.MULTILINE):
            columns.append(cm.group(1))

        for km in re.finditer(r"KEY `[^`]*` \(`(\w+)`\)", body):
            keyed_cols.add(km.group(1))
        for km in re.finditer(r"KEY `(\w+)` \(`(\w+)`\)", body):
            keyed_cols.add(km.group(2))

        tables[name] = {
            "columns": columns,
            "pk": pk_cols,
            "keyed": keyed_cols,
        }

    fks = {}
    for m in re.finditer(
        r"ADD CONSTRAINT `[^`]+` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)",
        text,
    ):
        child_col, parent_table, parent_col = m.group(1), m.group(2), m.group(3)
        fks.setdefault(parent_table, []).append(
            {"child_table": None, "child_col": child_col, "parent_col": parent_col}
        )

    # Resolve child tables for formal FKs
    formal = []
    for m in re.finditer(
        r"ALTER TABLE `(\w+)`\s+ADD CONSTRAINT `[^`]+` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)",
        text,
    ):
        formal.append(
            {
                "child_table": m.group(1),
                "child_col": m.group(2),
                "parent_table": m.group(3),
                "parent_col": m.group(4),
            }
        )

    logical = []
    for tname, tinfo in tables.items():
        for col in tinfo["columns"]:
            if col in tinfo["pk"]:
                continue
            parent = FK_COLUMN_MAP.get(col)
            if parent and parent in tables and parent != tname:
                parent_pk = next(iter(tables[parent]["pk"]), col)
                logical.append(
                    {
                        "child_table": tname,
                        "child_col": col,
                        "parent_table": parent,
                        "parent_col": parent_pk,
                    }
                )
            elif col.endswith("_id") and col not in FK_COLUMN_MAP:
                candidate = col[:-3] + "s"
                if candidate in tables and candidate != tname:
                    parent_pk = next(iter(tables[candidate]["pk"]), col)
                    logical.append(
                        {
                            "child_table": tname,
                            "child_col": col,
                            "parent_table": candidate,
                            "parent_col": parent_pk,
                        }
                    )

    # Merge relationships (dedupe)
    rels = {}
    for rel in formal + logical:
        parent_pk = next(iter(tables[rel["parent_table"]]["pk"]), rel.get("parent_col"))
        rel["parent_col"] = parent_pk
        key = (rel["parent_table"], rel["child_table"], rel["child_col"])
        rels[key] = rel
    return tables, list(rels.values())


def title_case_table(name: str) -> str:
    return " ".join(w.capitalize() for w in name.split("_"))


def build_fk_set(tables, relationships):
    fk = {}
    for rel in relationships:
        fk.setdefault(rel["child_table"], set()).add(rel["child_col"])
    return fk


def next_id(counter):
    counter[0] += 1
    return str(counter[0])


def add_table_cells(parent, counter, table_name, tinfo, fk_cols, x, y):
    cols = tinfo["columns"]
    width = 200
    height = 30 + len(cols) * 30
    tid = next_id(counter)
    table_ids = {"table": tid, "rows": {}, "pk_row": None}

    table_cell = ET.SubElement(
        parent,
        "mxCell",
        {
            "id": tid,
            "value": title_case_table(table_name),
            "style": TABLE_STYLE,
            "vertex": "1",
            "parent": "1",
        },
    )
    ET.SubElement(
        table_cell,
        "mxGeometry",
        {"x": str(x), "y": str(y), "width": str(width), "height": str(height), "as": "geometry"},
    )

    row_y = 30
    for i, col in enumerate(cols):
        is_pk = col in tinfo["pk"]
        is_fk = col in fk_cols
        row_style = PK_ROW_STYLE if is_pk else ROW_STYLE
        rid = next_id(counter)
        table_ids["rows"][col] = rid
        if is_pk:
            table_ids["pk_row"] = rid

        row = ET.SubElement(
            parent,
            "mxCell",
            {"id": rid, "value": "", "style": row_style, "vertex": "1", "parent": tid},
        )
        ET.SubElement(
            row,
            "mxGeometry",
            {"y": str(row_y), "width": str(width), "height": "30", "as": "geometry"},
        )

        mark = "PK" if is_pk else ("FK" if is_fk else "")
        mark_style = CELL_BASE + (";fontStyle=1" if mark else "")
        mark_cell = ET.SubElement(
            parent,
            "mxCell",
            {
                "id": next_id(counter),
                "value": mark,
                "style": mark_style,
                "vertex": "1",
                "parent": rid,
            },
        )
        ET.SubElement(
            mark_cell,
            "mxGeometry",
            {"width": "30", "height": "30", "as": "geometry"},
        )
        ET.SubElement(
            mark_cell,
            "mxRectangle",
            {"width": "30", "height": "30", "as": "alternateBounds"},
        )

        name_style = CELL_BASE + ";spacingLeft=6" + (";fontStyle=5" if is_pk else "")
        name_cell = ET.SubElement(
            parent,
            "mxCell",
            {
                "id": next_id(counter),
                "value": col,
                "style": name_style,
                "vertex": "1",
                "parent": rid,
            },
        )
        ET.SubElement(
            name_cell,
            "mxGeometry",
            {"x": "30", "width": str(width - 30), "height": "30", "as": "geometry"},
        )
        ET.SubElement(
            name_cell,
            "mxRectangle",
            {"width": str(width - 30), "height": "30", "as": "alternateBounds"},
        )
        row_y += 30

    return table_ids


def main():
    tables, relationships = parse_sql(SQL_PATH)
    fk_cols = build_fk_set(tables, relationships)

    counter = [1000]
    root = ET.Element(
        "mxfile",
        {"host": "app.diagrams.net", "agent": "generate_erd.py", "version": "24.7.17"},
    )
    diagram = ET.SubElement(root, "diagram", {"id": "ned-sems-erd", "name": "NED-SEMs ERD"})
    model = ET.SubElement(
        diagram,
        "mxGraphModel",
        {
            "dx": "2400",
            "dy": "1600",
            "grid": "1",
            "gridSize": "10",
            "guides": "1",
            "tooltips": "1",
            "connect": "1",
            "arrows": "1",
            "fold": "1",
            "page": "1",
            "pageScale": "1",
            "pageWidth": "3200",
            "pageHeight": "2400",
            "math": "0",
            "shadow": "0",
        },
    )
    groot = ET.SubElement(model, "root")
    ET.SubElement(groot, "mxCell", {"id": "0"})
    ET.SubElement(groot, "mxCell", {"id": "1", "parent": "0"})

    table_map = {}
    order = sorted(tables.keys())
    auto_x, auto_y = 1300, 40
    for tname in order:
        if tname in LAYOUT:
            x, y = LAYOUT[tname]
        else:
            x, y = auto_x, auto_y
            auto_y += 40 + len(tables[tname]["columns"]) * 30 + 40
            if auto_y > 2200:
                auto_y = 40
                auto_x += 260
        table_map[tname] = add_table_cells(
            groot, counter, tname, tables[tname], fk_cols.get(tname, set()), x, y
        )

    seen_edges = set()
    for rel in relationships:
        pt = rel["parent_table"]
        ct = rel["child_table"]
        cc = rel["child_col"]
        key = (pt, ct, cc)
        if key in seen_edges:
            continue
        seen_edges.add(key)

        source_row = table_map[pt]["pk_row"] or table_map[pt]["rows"].get(
            rel["parent_col"]
        )
        target_row = table_map[ct]["rows"].get(cc)
        if not source_row or not target_row:
            continue

        eid = next_id(counter)
        edge = ET.SubElement(
            groot,
            "mxCell",
            {
                "id": eid,
                "value": "",
                "style": EDGE_STYLE,
                "edge": "1",
                "parent": "1",
                "source": source_row,
                "target": target_row,
            },
        )
        ET.SubElement(edge, "mxGeometry", {"relative": "1", "as": "geometry"})

    tree = ET.ElementTree(root)
    ET.indent(tree, space="    ")
    tree.write(OUTPUT_PATH, encoding="unicode", xml_declaration=False)
    # draw.io expects no XML declaration in some versions; prepend mxfile directly
    content = OUTPUT_PATH.read_text(encoding="utf-8")
    if not content.startswith("<mxfile"):
        content = content.split("?>", 1)[-1].lstrip()
        OUTPUT_PATH.write_text(content, encoding="utf-8")

    print(f"Generated {OUTPUT_PATH}")
    print(f"Tables: {len(tables)}")
    print(f"Relationships: {len(seen_edges)}")


if __name__ == "__main__":
    main()
