DROP FUNCTION seocalc_update_spywords(VARCHAR(255), VARCHAR[]);

CREATE FUNCTION seocalc_update_spywords(VARCHAR(255), VARCHAR[]) RETURNS VOID AS $$
DECLARE
  dom ALIAS FOR $1;
  arr_phrases ALIAS FOR $2;
  p VARCHAR[];
  id_domain INT;
BEGIN

END;
$$ LANGUAGE plpgsql;