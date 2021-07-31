create view list_permissions
as
select distinct a.menu_name,
                a.app_name,
                a.parent_id,
                a.icon,
                a.route_name,
                a.has_child,
                a.has_route,
                a.is_crud,
                a.order_line,
                b.menu_name as parena_name,
                idx.valuex   as 'index',
                store.valuex as store,
                edits.valuex as edits,
                erase.valuex as erase
from permissions as a
         left join permissions as b on a.parent_id = b.id
         left join (
    select menu_name, 'Y' as valuex
    from permissions as a1
    where RIGHT(a1.name, 5) = 'index'

) as idx on a.menu_name = idx.menu_name

         left join (
    select menu_name, 'Y' as valuex
    from permissions as a1
    where  RIGHT(a1.name, 5) = 'store'

) as store on a.menu_name = store.menu_name

         left join (
    select menu_name, 'Y' as valuex
    from permissions as a1
    where RIGHT(a1.name, 5) = 'edits'
) as edits on a.menu_name = edits.menu_name

         left join (
    select menu_name, 'Y' as valuex
    from permissions as a1
    where RIGHT(a1.name, 5) = 'erase'
) as erase on a.menu_name = erase.menu_name
